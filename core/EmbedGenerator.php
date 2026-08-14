<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Generates embed snippets for forms (PRD §5.4).
 */
class EmbedGenerator
{
  /**
   * @param array<string, mixed> $form
   * @param array<string, mixed> $config
   * @return array{html: string, fetch: string, endpoint: string}
   */
  public static function generate(array $form, array $config): array
  {
    $baseUrl = rtrim((string) ($config['app']['url'] ?? ''), '/');
    $slug = (string) ($form['slug'] ?? '');
    $endpoint = $baseUrl . '/submit/' . rawurlencode($slug);

    $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
    $htmlFields = [];
    $jsonFields = [];

    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }

      $type = (string) ($field['type'] ?? 'text');
      $id = (string) ($field['id'] ?? '');
      $label = (string) ($field['label'] ?? '');
      $required = !empty($field['required']);

      if ($type === 'heading') {
        $htmlFields[] = '  <h3>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h3>';
        continue;
      }

      if ($type === 'paragraph') {
        $htmlFields[] = '  <p>' . htmlspecialchars((string) ($field['default'] ?? $label), ENT_QUOTES, 'UTF-8') . '</p>';
        continue;
      }

      if ($id === '') {
        continue;
      }

      $name = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $placeholder = htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8');
      $req = $required ? ' required' : '';

      $jsonFields[$id] = $label;

      switch ($type) {
        case 'textarea':
          $htmlFields[] = "  <label>{$labelEsc}<br><textarea name=\"{$name}\" placeholder=\"{$placeholder}\"{$req}></textarea></label>";
          break;
        case 'select':
          $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
          $optHtml = '';
          foreach ($opts as $opt) {
            if (!is_array($opt)) {
              continue;
            }
            $v = htmlspecialchars((string) ($opt['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $l = htmlspecialchars((string) ($opt['label'] ?? $v), ENT_QUOTES, 'UTF-8');
            $optHtml .= "<option value=\"{$v}\">{$l}</option>";
          }
          $htmlFields[] = "  <label>{$labelEsc}<br><select name=\"{$name}\"{$req}>{$optHtml}</select></label>";
          break;
        case 'checkbox':
          $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
          $boxes = '';
          foreach ($opts as $opt) {
            if (!is_array($opt)) {
              continue;
            }
            $v = htmlspecialchars((string) ($opt['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $l = htmlspecialchars((string) ($opt['label'] ?? $v), ENT_QUOTES, 'UTF-8');
            $boxes .= "<label><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$v}\"> {$l}</label> ";
          }
          $htmlFields[] = "  <fieldset><legend>{$labelEsc}</legend>{$boxes}</fieldset>";
          break;
        case 'radio':
          $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
          $radios = '';
          foreach ($opts as $opt) {
            if (!is_array($opt)) {
              continue;
            }
            $v = htmlspecialchars((string) ($opt['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $l = htmlspecialchars((string) ($opt['label'] ?? $v), ENT_QUOTES, 'UTF-8');
            $radios .= "<label><input type=\"radio\" name=\"{$name}\" value=\"{$v}\"{$req}> {$l}</label> ";
          }
          $htmlFields[] = "  <fieldset><legend>{$labelEsc}</legend>{$radios}</fieldset>";
          break;
        case 'single-checkbox':
          $htmlFields[] = "  <label><input type=\"checkbox\" name=\"{$name}\" value=\"1\"{$req}> {$labelEsc}</label>";
          break;
        case 'hidden':
          $val = htmlspecialchars((string) ($field['default'] ?? ''), ENT_QUOTES, 'UTF-8');
          $htmlFields[] = "  <input type=\"hidden\" name=\"{$name}\" value=\"{$val}\">";
          break;
        case 'file':
          $htmlFields[] = "  <label>{$labelEsc}<br><input type=\"file\" name=\"{$name}\"{$req}></label>";
          break;
        default:
          $inputType = in_array($type, ['email', 'number', 'phone', 'url', 'date', 'time'], true) ? $type : 'text';
          if ($inputType === 'phone') {
            $inputType = 'tel';
          }
          $htmlFields[] = "  <label>{$labelEsc}<br><input type=\"{$inputType}\" name=\"{$name}\" placeholder=\"{$placeholder}\"{$req}></label>";
      }
    }

  $honeypot = '';
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $spam = is_array($settings['spam'] ?? null) ? $settings['spam'] : [];
    if (!empty($spam['honeypot'])) {
      $honeypot = "  <!-- Honeypot -->\n  <div style=\"display:none\"><label>Leave blank<input type=\"text\" name=\"_honeypot\" tabindex=\"-1\" autocomplete=\"off\"></label></div>";
    }

    $html = "<form action=\"{$endpoint}\" method=\"POST\" enctype=\"multipart/form-data\">\n"
      . implode("\n", $htmlFields) . "\n"
      . $honeypot . "\n"
      . "  <button type=\"submit\">Submit</button>\n"
      . '</form>';

    $jsonExample = json_encode($jsonFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $fetch = "fetch('{$endpoint}', {\n"
      . "  method: 'POST',\n"
      . "  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },\n"
      . "  body: JSON.stringify(" . $jsonExample . ")\n"
      . "})\n"
      . "  .then(r => r.json())\n"
      . "  .then(data => console.log(data))\n"
      . "  .catch(err => console.error(err));";

    return ['html' => $html, 'fetch' => $fetch, 'endpoint' => $endpoint];
  }
}
