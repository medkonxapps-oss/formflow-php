<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Seeds bundled form templates into form_templates table.
 */
class TemplateSeeder
{
  /**
   * @param array<string, mixed> $config
   */
  public static function run(array $config): int
  {
    $db = Database::getInstance($config);
    $tbl = Db::table('form_templates', $config);
    $inserted = 0;

    foreach (TemplateDefinitions::all() as $tpl) {
      $exists = $db->fetchOne("SELECT id FROM {$tbl} WHERE slug = ? LIMIT 1", [$tpl['slug']]);
      if ($exists !== null) {
        continue;
      }

      $db->query(
        "INSERT INTO {$tbl} (slug, name, description, category, fields_json, settings_json, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        [
          $tpl['slug'],
          $tpl['name'],
          $tpl['description'],
          $tpl['category'],
          JsonColumn::encode($tpl['fields']),
          JsonColumn::encode($tpl['settings']),
          (int) $tpl['sort_order'],
        ]
      );
      $inserted++;
    }

    return $inserted;
  }
}
