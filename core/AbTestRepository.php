<?php

declare(strict_types=1);

namespace FormFlow;

use PDO;

/**
 * A/B Test data layer.
 * Handles variant CRUD, sticky session assignment, and conversion recording.
 */
class AbTestRepository
{
    private Database $db;

    private string $tblVariants;

    private string $tblSessions;

    private string $tblConversions;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->db = Database::getInstance($config);
        $this->tblVariants = Db::table('form_variants', $config);
        $this->tblSessions = Db::table('form_variant_sessions', $config);
        $this->tblConversions = Db::table('form_variant_conversions', $config);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Variant management
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return all variants for a form (ordered: control first, then by id).
     * @return list<array<string, mixed>>
     */
    public function getVariantsForForm(int $formId): array
    {
        $stmt = $this->db->query(
            "SELECT id, form_id, name, is_control, traffic_pct, fields_json, settings_json, created_at
             FROM {$this->tblVariants}
             WHERE form_id = ?
             ORDER BY is_control DESC, id ASC",
            [$formId]
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Upsert variants for a form.
     * Replaces all non-control variants; the control row mirrors the main form.
     *
     * @param list<array{id?: int, name: string, traffic_pct: int, fields_json?: string|null, settings_json?: string|null}> $variants
     */
    public function saveVariants(int $formId, array $variants): void
    {
        $control = $this->db->fetchOne(
            "SELECT id FROM {$this->tblVariants} WHERE form_id = ? AND is_control = 1",
            [$formId]
        );

        if ($control === null) {
            $this->db->query(
                "INSERT INTO {$this->tblVariants} (form_id, name, is_control, traffic_pct) VALUES (?, ?, 1, 50)",
                [$formId, 'Control']
            );
        }

        $this->db->query(
            "DELETE FROM {$this->tblVariants} WHERE form_id = ? AND is_control = 0",
            [$formId]
        );

        foreach ($variants as $v) {
            if (!is_array($v) || (int) ($v['is_control'] ?? 0) === 1) {
                continue;
            }
            $fieldsJson = $v['fields_json'] ?? null;
            if (is_array($v['fields'] ?? null)) {
                $fieldsJson = json_encode($v['fields']);
            }
            if (!is_string($fieldsJson) || $fieldsJson === '') {
                $fieldsJson = null;
            }

            $this->db->query(
                "INSERT INTO {$this->tblVariants} (form_id, name, is_control, traffic_pct, fields_json, settings_json)
                 VALUES (?, ?, 0, ?, ?, ?)",
                [
                    $formId,
                    substr(trim((string) ($v['name'] ?? 'Variant')), 0, 100),
                    max(0, min(100, (int) ($v['traffic_pct'] ?? 50))),
                    $fieldsJson,
                    isset($v['settings_json']) && is_string($v['settings_json']) && $v['settings_json'] !== ''
                        ? $v['settings_json']
                        : null,
                ]
            );
        }

        $this->rebalanceControl($formId);
    }

    public function enableForForm(int $formId): void
    {
        $formRow = $this->db->fetchOne(
            'SELECT settings_json FROM ' . Db::table('forms', $this->config) . ' WHERE id = ?',
            [$formId]
        );
        if ($formRow === null) {
            return;
        }
        $settings = json_decode((string) ($formRow['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['ab_test'] = ['enabled' => true];
        $this->db->query(
            'UPDATE ' . Db::table('forms', $this->config) . ' SET settings_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [json_encode($settings), $formId]
        );
    }

    /**
     * Update just the control variant's traffic weight.
     */
    private function rebalanceControl(int $formId): void
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(traffic_pct), 0) AS used
             FROM {$this->tblVariants}
             WHERE form_id = ? AND is_control = 0",
            [$formId]
        );
        $used = (int) ($row['used'] ?? 0);
        $controlPct = max(0, 100 - $used);
        $this->db->query(
            "UPDATE {$this->tblVariants} SET traffic_pct = ? WHERE form_id = ? AND is_control = 1",
            [$controlPct, $formId]
        );
    }

    /**
     * Merge winning variant fields/settings back into the main form, then disable A/B test.
     */
    public function declareWinner(int $variantId, int $formId, int $userId): bool
    {
        $variant = $this->db->fetchOne(
            "SELECT * FROM {$this->tblVariants} WHERE id = ? AND form_id = ?",
            [$variantId, $formId]
        );

        if ($variant === null) {
            return false;
        }

        // If winner is a non-control variant, copy its fields/settings to the main form
        if (!$variant['is_control'] && $variant['fields_json'] !== null) {
            $this->db->query(
                'UPDATE forms SET fields_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [$variant['fields_json'], $formId]
            );
        }
        if (!$variant['is_control'] && $variant['settings_json'] !== null) {
            // Merge ab_test.enabled = false into existing settings
            $formRow = $this->db->fetchOne('SELECT settings_json FROM forms WHERE id = ?', [$formId]);
            $settings = json_decode((string) ($formRow['settings_json'] ?? '{}'), true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $variantSettings = json_decode((string) $variant['settings_json'], true);
            if (is_array($variantSettings)) {
                $settings = array_merge($settings, $variantSettings);
            }
            $settings['ab_test']['enabled'] = false;
            $this->db->query(
                'UPDATE forms SET settings_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [json_encode($settings), $formId]
            );
        } else {
            // Just disable A/B test in settings
            $formRow = $this->db->fetchOne('SELECT settings_json FROM forms WHERE id = ?', [$formId]);
            $settings = json_decode((string) ($formRow['settings_json'] ?? '{}'), true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings['ab_test']['enabled'] = false;
            $this->db->query(
                'UPDATE forms SET settings_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
                [json_encode($settings), $formId]
            );
        }

        // Remove all variants
        $this->db->query("DELETE FROM {$this->tblVariants} WHERE form_id = ?", [$formId]);

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Visitor assignment (sticky)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>|null
     */
    public function findVariant(int $formId, int $variantId): ?array
    {
        if ($variantId <= 0) {
            return null;
        }

        return $this->db->fetchOne(
            "SELECT * FROM {$this->tblVariants} WHERE form_id = ? AND id = ? LIMIT 1",
            [$formId, $variantId]
        );
    }

    /**
     * Get or assign a variant for this visitor session.
     * Uses weighted random selection; once assigned, same variant is always returned.
     *
     * @return array<string, mixed>|null  variant row, or null if A/B not set up
     */
    public function assignVariant(int $formId, string $sessionToken): ?array
    {
        $tokenHash = hash('sha256', $sessionToken);

        // Check existing assignment
        $existing = $this->db->fetchOne(
            "SELECT fv.* FROM {$this->tblSessions} fvs
             JOIN {$this->tblVariants} fv ON fv.id = fvs.variant_id
             WHERE fvs.form_id = ? AND fvs.session_token = ?",
            [$formId, $tokenHash]
        );

        if ($existing !== null) {
            return $existing;
        }

        // Load variants
        $variants = $this->getVariantsForForm($formId);
        if (count($variants) < 2) {
            return null;
        }

        // Weighted random pick
        $chosen = $this->weightedPick($variants);
        if ($chosen === null) {
            return null;
        }

        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

        // Record assignment (ignore duplicate key on race condition)
        try {
            $this->db->query(
                "INSERT IGNORE INTO {$this->tblSessions} (form_id, variant_id, session_token, ip_hash)
                 VALUES (?, ?, ?, ?)",
                [$formId, (int) $chosen['id'], $tokenHash, $ipHash]
            );
        } catch (\Throwable) {
            // Race condition: re-read
            $existing = $this->db->fetchOne(
                "SELECT fv.* FROM {$this->tblSessions} fvs
                 JOIN {$this->tblVariants} fv ON fv.id = fvs.variant_id
                 WHERE fvs.form_id = ? AND fvs.session_token = ?",
                [$formId, $tokenHash]
            );
            return $existing ?: $chosen;
        }

        return $chosen;
    }

    /**
     * @param list<array<string, mixed>> $variants
     * @return array<string, mixed>|null
     */
    private function weightedPick(array $variants): ?array
    {
        $total = array_sum(array_column($variants, 'traffic_pct'));
        if ($total <= 0) {
            return $variants[0] ?? null;
        }

        $rand = random_int(1, (int) $total);
        $cumulative = 0;
        foreach ($variants as $v) {
            $cumulative += (int) $v['traffic_pct'];
            if ($rand <= $cumulative) {
                return $v;
            }
        }
        return $variants[array_key_last($variants)] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Conversion tracking
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Record a conversion for the variant this session was assigned to.
     */
    public function recordConversion(int $formId, string $sessionToken, int $submissionId): void
    {
        $tokenHash = hash('sha256', $sessionToken);

        $session = $this->db->fetchOne(
            "SELECT variant_id FROM {$this->tblSessions}
             WHERE form_id = ? AND session_token = ?",
            [$formId, $tokenHash]
        );

        if ($session === null) {
            return;
        }

        $this->db->query(
            "INSERT IGNORE INTO {$this->tblConversions} (variant_id, submission_id)
             VALUES (?, ?)",
            [(int) $session['variant_id'], $submissionId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stats / Results
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return per-variant stats: impressions, conversions, conversion_rate.
     * @return list<array{id: int, name: string, is_control: int, traffic_pct: int, impressions: int, conversions: int, conversion_rate: float}>
     */
    public function getStats(int $formId): array
    {
        $stmt = $this->db->query(
            'SELECT
               fv.id,
               fv.name,
               fv.is_control,
               fv.traffic_pct,
               COUNT(DISTINCT fvs.id)  AS impressions,
               COUNT(DISTINCT fvc.id)  AS conversions
             FROM {$this->tblVariants} fv
             LEFT JOIN {$this->tblSessions}    fvs ON fvs.variant_id = fv.id
             LEFT JOIN {$this->tblConversions} fvc ON fvc.variant_id = fv.id
             WHERE fv.form_id = ?
             GROUP BY fv.id
             ORDER BY fv.is_control DESC, fv.id ASC',
            [$formId]
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(function (array $r): array {
            $imp  = (int) $r['impressions'];
            $conv = (int) $r['conversions'];
            return [
                'id'              => (int)   $r['id'],
                'name'            => (string) $r['name'],
                'is_control'      => (int)   $r['is_control'],
                'traffic_pct'     => (int)   $r['traffic_pct'],
                'impressions'     => $imp,
                'conversions'     => $conv,
                'conversion_rate' => $imp > 0 ? round($conv / $imp * 100, 2) : 0.0,
            ];
        }, $rows);
    }

    /**
     * Chi-square significance test (p < 0.05 = significant).
     * Returns 'winner_id' if a clear winner exists, else null.
     *
     * @param list<array{id: int, impressions: int, conversions: int, conversion_rate: float}> $stats
     */
    public function detectWinner(array $stats): ?int
    {
        if (count($stats) < 2) {
            return null;
        }

        $best = null;
        foreach ($stats as $s) {
            if ($best === null || $s['conversion_rate'] > $best['conversion_rate']) {
                $best = $s;
            }
        }

        if ($best === null || $best['impressions'] < 30) {
            return null; // not enough data
        }

        // Simple z-test vs control
        $control = null;
        foreach ($stats as $s) {
            if ($s['is_control']) {
                $control = $s;
                break;
            }
        }

        if ($control === null || $best['id'] === $control['id']) {
            return null;
        }

        $n1 = $control['impressions'];
        $n2 = $best['impressions'];
        if ($n1 < 1 || $n2 < 1) {
            return null;
        }

        $p1 = $control['conversion_rate'] / 100;
        $p2 = $best['conversion_rate'] / 100;
        $pPool = ($control['conversions'] + $best['conversions']) / ($n1 + $n2);

        if ($pPool <= 0 || $pPool >= 1) {
            return null;
        }

        $se = sqrt($pPool * (1 - $pPool) * (1 / $n1 + 1 / $n2));
        if ($se <= 0) {
            return null;
        }

        $z = abs($p2 - $p1) / $se;
        // z > 1.96 → p < 0.05 (95% confidence)
        return $z > 1.96 && $p2 > $p1 ? (int) $best['id'] : null;
    }
}
