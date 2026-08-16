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
      $params = [
        $tpl['name'],
        $tpl['description'],
        $tpl['category'],
        JsonColumn::encode($tpl['fields']),
        JsonColumn::encode($tpl['settings']),
        (int) $tpl['sort_order'],
        $tpl['slug'],
      ];

      $exists = $db->fetchOne("SELECT id FROM {$tbl} WHERE slug = ? LIMIT 1", [$tpl['slug']]);
      if ($exists !== null) {
        $db->query(
          "UPDATE {$tbl}
           SET name = ?, description = ?, category = ?, fields_json = ?, settings_json = ?, sort_order = ?
           WHERE slug = ?",
          $params
        );
        continue;
      }

      $db->query(
        "INSERT INTO {$tbl} (name, description, category, fields_json, settings_json, sort_order, slug, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        $params
      );
      $inserted++;
    }

    return $inserted;
  }
}
