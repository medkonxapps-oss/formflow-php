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
   * @return array{
   *   html: string,
   *   fetch: string,
   *   endpoint: string,
   *   hosted: string,
   *   iframe: string,
   *   popup: string,
   *   script: string,
   *   curl: string
   * }
   */
  public static function generate(array $form, array $config): array
  {
    $baseUrl = rtrim((string) ($config['app']['url'] ?? ''), '/');
    if ($baseUrl === '' && function_exists('app_url')) {
      $baseUrl = rtrim(app_url($config, '/'), '/');
    }
    $slug = (string) ($form['slug'] ?? '');
    $endpoint = $baseUrl . '/submit/' . rawurlencode($slug);
    $hosted = $baseUrl . '/preview/' . rawurlencode($slug);

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : FormDefaults::settings();
    $theme = is_array($settings['theme'] ?? null) ? $settings['theme'] : (FormDefaults::settings()['theme'] ?? []);
    $btnText = htmlspecialchars((string) ($theme['button_text'] ?? 'Submit'), ENT_QUOTES, 'UTF-8');
    $btnColor = htmlspecialchars((string) ($theme['button_color'] ?? '#2563eb'), ENT_QUOTES, 'UTF-8');
    $bgColor = htmlspecialchars((string) ($theme['background_color'] ?? '#ffffff'), ENT_QUOTES, 'UTF-8');
    $labelColor = htmlspecialchars((string) ($theme['label_color'] ?? '#374151'), ENT_QUOTES, 'UTF-8');
    $radius = (int) ($theme['border_radius'] ?? 8);
    $maxWidth = (int) ($theme['max_width'] ?? 600);
    $fontFamily = htmlspecialchars((string) ($theme['font_family'] ?? 'inherit'), ENT_QUOTES, 'UTF-8');

    $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
    $htmlFields = [];
    $jsonFields = [];
    $conditionals = [];

    foreach ($fields as $field) {
      if (!is_array($field)) {
        continue;
      }

      $type = (string) ($field['type'] ?? 'text');
      $id = (string) ($field['id'] ?? '');
      $label = (string) ($field['label'] ?? '');
      $required = !empty($field['required']);
      $helpText = trim((string) ($field['help_text'] ?? ''));
      $style = is_array($field['style'] ?? null) ? $field['style'] : [];
      $cssClass = htmlspecialchars(trim((string) ($style['css_class'] ?? '')), ENT_QUOTES, 'UTF-8');
      $labelBold = !empty($style['label_bold']);
      $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
      $conditional = is_array($field['conditional'] ?? null) ? $field['conditional'] : [];

      if (!empty($conditional['enabled']) && !empty($conditional['rules'])) {
        $conditionals[$id] = $conditional;
      }

      $classAttr = 'ff-field' . ($cssClass !== '' ? ' ' . $cssClass : '');
      $wrapOpen = $id !== '' ? '  <div class="' . $classAttr . '" data-ff-field="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' : '';
      $wrapClose = $id !== '' ? '  </div>' : '';

      if ($type === 'heading') {
        $htmlFields[] = $wrapOpen . "\n    <h3 style=\"color:{$labelColor};font-weight:600\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</h3>\n" . $wrapClose;
        continue;
      }

      if ($type === 'paragraph') {
        $htmlFields[] = $wrapOpen . "\n    <p style=\"color:{$labelColor}\">" . htmlspecialchars((string) ($field['default'] ?? $label), ENT_QUOTES, 'UTF-8') . "</p>\n" . $wrapClose;
        continue;
      }

      if ($id === '') {
        continue;
      }

      $name = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
      $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      $placeholder = htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8');
      $default = htmlspecialchars((string) ($field['default'] ?? ''), ENT_QUOTES, 'UTF-8');
      $req = $required ? ' required' : '';
      $reqMark = $required ? ' <span style="color:#ef4444">*</span>' : '';
      $labelStyle = 'color:' . $labelColor . ';font-weight:' . ($labelBold ? '600' : '500');
      $helpHtml = $helpText !== '' ? "<small style=\"display:block;color:#6b7280;margin:0.25rem 0\">" . htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8') . '</small>' : '';

      $jsonFields[$id] = $label;

      $inner = '';
      switch ($type) {
        case 'textarea':
          $inner = "    <label style=\"{$labelStyle}\">{$labelEsc}{$reqMark}</label>{$helpHtml}<textarea name=\"{$name}\" placeholder=\"{$placeholder}\"{$req} style=\"border-radius:{$radius}px\">{$default}</textarea>";
          break;
        case 'select':
          $opts = is_array($field['options'] ?? null) ? $field['options'] : [];
          $optHtml = '<option value="">Select...</option>';
          foreach ($opts as $opt) {
            if (!is_array($opt)) {
              continue;
            }
            $v = htmlspecialchars((string) ($opt['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $l = htmlspecialchars((string) ($opt['label'] ?? $v), ENT_QUOTES, 'UTF-8');
            $sel = $default === $v ? ' selected' : '';
            $optHtml .= "<option value=\"{$v}\"{$sel}>{$l}</option>";
          }
          $inner = "    <label style=\"{$labelStyle}\">{$labelEsc}{$reqMark}</label>{$helpHtml}<select name=\"{$name}\"{$req} style=\"border-radius:{$radius}px\">{$optHtml}</select>";
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
          $inner = "    <fieldset><legend style=\"{$labelStyle}\">{$labelEsc}</legend>{$helpHtml}{$boxes}</fieldset>";
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
            $chk = $default === $v ? ' checked' : '';
            $radios .= "<label><input type=\"radio\" name=\"{$name}\" value=\"{$v}\"{$req}{$chk}> {$l}</label> ";
          }
          $inner = "    <fieldset><legend style=\"{$labelStyle}\">{$labelEsc}</legend>{$helpHtml}{$radios}</fieldset>";
          break;
        case 'single-checkbox':
          $inner = "    <label style=\"{$labelStyle}\"><input type=\"checkbox\" name=\"{$name}\" value=\"1\"{$req}> {$labelEsc}</label>{$helpHtml}";
          break;
        case 'hidden':
          $inner = "    <input type=\"hidden\" name=\"{$name}\" value=\"{$default}\">";
          break;
        case 'file':
          $accept = trim((string) ($validation['accept'] ?? ''));
          $acceptAttr = $accept !== '' ? ' accept="' . htmlspecialchars($accept, ENT_QUOTES, 'UTF-8') . '"' : '';
          $inner = "    <label style=\"{$labelStyle}\">{$labelEsc}{$reqMark}</label>{$helpHtml}<input type=\"file\" name=\"{$name}\"{$req}{$acceptAttr}>";
          break;
        default:
          $inputType = in_array($type, ['email', 'number', 'phone', 'url', 'date', 'time'], true) ? $type : 'text';
          if ($inputType === 'phone') {
            $inputType = 'tel';
          }
          $extra = '';
          if ($inputType === 'number') {
            if (($validation['min_value'] ?? null) !== null && $validation['min_value'] !== '') {
              $extra .= ' min="' . htmlspecialchars((string) $validation['min_value'], ENT_QUOTES, 'UTF-8') . '"';
            }
            if (($validation['max_value'] ?? null) !== null && $validation['max_value'] !== '') {
              $extra .= ' max="' . htmlspecialchars((string) $validation['max_value'], ENT_QUOTES, 'UTF-8') . '"';
            }
          }
          $valAttr = $default !== '' ? " value=\"{$default}\"" : '';
          $inner = "    <label style=\"{$labelStyle}\">{$labelEsc}{$reqMark}</label>{$helpHtml}<input type=\"{$inputType}\" name=\"{$name}\" placeholder=\"{$placeholder}\"{$req}{$valAttr}{$extra} style=\"border-radius:{$radius}px\">";
      }

      if ($type === 'hidden') {
        $htmlFields[] = $inner;
      } else {
        $htmlFields[] = $wrapOpen . "\n" . $inner . "\n" . $wrapClose;
      }
    }

    $spam = is_array($settings['spam'] ?? null) ? $settings['spam'] : [];
    $honeypot = '';
    if (!empty($spam['honeypot'])) {
      $honeypot = "  <div style=\"display:none\"><label>Leave blank<input type=\"text\" name=\"_honeypot\" tabindex=\"-1\" autocomplete=\"off\"></label></div>";
    }

    $formStyle = "max-width:{$maxWidth}px;margin:0 auto;padding:1.5rem;background:{$bgColor};border-radius:{$radius}px;font-family:{$fontFamily}";
    $btnStyle = "width:100%;padding:0.75rem;background:{$btnColor};color:#fff;border:none;border-radius:{$radius}px;font-weight:600;cursor:pointer;margin-top:0.5rem";

    $logicScript = '';
    if ($conditionals !== []) {
      $rulesJson = json_encode($conditionals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
      $logicScript = "\n<script>\n(function(){\n"
        . "var rules={$rulesJson};\n"
        . "var form=document.currentScript.previousElementSibling;\n"
        . "if(!form||form.tagName!=='FORM')form=document.querySelector('.ff-form');\n"
        . "function gv(id){var el=form.querySelector('[name=\"'+id+'\"]')||form.querySelector('[name=\"'+id+'[]\"]');if(!el)return'';if(el.name&&el.name.endsWith('[]')){return Array.from(form.querySelectorAll('[name=\"'+id+'[]\"]:checked')).map(function(c){return c.value;}).join(',');}if(el.type==='checkbox')return el.checked?'1':'';return el.value||'';}\n"
        . "function evalRule(r){var v=gv(r.field_id),op=r.operator,val=r.value||'';switch(op){case 'equals':return v===val;case 'not_equals':return v!==val;case 'contains':return v.indexOf(val)!==-1;case 'not_empty':return v.trim()!=='';case 'empty':return v.trim()==='';default:return true;}}\n"
        . "function update(){Object.keys(rules).forEach(function(fid){var c=rules[fid],wrap=form.querySelector('[data-ff-field=\"'+fid+'\"]');if(!wrap)return;var res=c.rules.map(evalRule),pass=c.match==='any'?res.some(Boolean):res.every(Boolean),show=c.action==='show'?pass:!pass;wrap.style.display=show?'':'none';});}\n"
        . "form.addEventListener('input',update);form.addEventListener('change',update);update();\n"
        . "})();\n</script>";
    }

    $trackUrl = $baseUrl . '/track/' . rawurlencode($slug);
    $trackScript = "\n<script>(function(){try{fetch(" . json_encode($trackUrl) . ",{method:'POST',mode:'no-cors',keepalive:true});}catch(e){}}())</script>";

    $html = "<form class=\"ff-form\" action=\"{$endpoint}\" method=\"POST\" enctype=\"multipart/form-data\" style=\"{$formStyle}\">\n"
      . implode("\n", $htmlFields) . "\n"
      . $honeypot . "\n"
      . "  <button type=\"submit\" style=\"{$btnStyle}\">{$btnText}</button>\n"
      . '</form>' . $logicScript . $trackScript;

    $jsonExample = json_encode($jsonFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $fetch = "fetch(" . json_encode($endpoint) . ", {\n"
      . "  method: 'POST',\n"
      . "  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },\n"
      . "  body: JSON.stringify(" . $jsonExample . ")\n"
      . "})\n"
      . "  .then(r => r.json())\n"
      . "  .then(data => console.log(data))\n"
      . "  .catch(err => console.error(err));";

    $title = htmlspecialchars((string) ($form['name'] ?? 'Form'), ENT_QUOTES, 'UTF-8');
    $hostedEsc = htmlspecialchars($hosted, ENT_QUOTES, 'UTF-8');
    $iframe = '<iframe src="' . $hostedEsc . '" title="' . $title . '" width="100%" height="720" style="max-width:' . $maxWidth . 'px;border:0;border-radius:' . $radius . 'px" loading="lazy"></iframe>';

    $uid = 'ff_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $slug);
    $script = '<div id="' . $uid . '"></div>' . "\n"
      . '<script>' . "\n"
      . '(function(){var h=document.getElementById(' . json_encode($uid) . ');if(!h)return;'
      . 'var i=document.createElement("iframe");'
      . 'i.src=' . json_encode($hosted) . ';'
      . 'i.title=' . json_encode((string) ($form['name'] ?? 'Form')) . ';'
      . 'i.setAttribute("loading","lazy");'
      . 'i.style.cssText="width:100%;max-width:' . $maxWidth . 'px;height:720px;border:0;border-radius:' . $radius . 'px";'
      . 'h.appendChild(i);})();' . "\n"
      . '</script>';

    $popup = '<button type="button" onclick="document.getElementById(\'' . $uid . '_m\').style.display=\'flex\'" style="padding:0.75rem 1.25rem;background:' . $btnColor . ';color:#fff;border:none;border-radius:' . $radius . 'px;font-weight:600;cursor:pointer">' . $btnText . '</button>' . "\n"
      . '<div id="' . $uid . '_m" style="display:none;position:fixed;inset:0;background:rgba(15,15,20,.55);z-index:99999;align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)this.style.display=\'none\'">' . "\n"
      . '  <iframe src="' . $hostedEsc . '" title="' . $title . '" style="width:min(' . $maxWidth . 'px,96vw);height:min(80vh,720px);border:0;border-radius:' . $radius . 'px;background:#fff"></iframe>' . "\n"
      . '</div>';

    $curl = 'curl -X POST ' . json_encode($endpoint) . " \\\n"
      . "  -H \"Content-Type: application/json\" \\\n"
      . "  -H \"Accept: application/json\" \\\n"
      . '  -d \'' . str_replace("'", "'\\''", (string) json_encode($jsonFields, JSON_UNESCAPED_UNICODE)) . "'";

    $snippetHtml = $html;
    if (!empty($settings['ab_test']['enabled'])) {
      $snippetHtml = $iframe . "\n<!-- FormFlow A/B: use iframe so visitors get a variant. Do not nest this on /preview. -->";
    }

    return [
      'html' => $snippetHtml,
      'inline_html' => $html,
      'fetch' => $fetch,
      'endpoint' => $endpoint,
      'hosted' => $hosted,
      'iframe' => $iframe,
      'popup' => $popup,
      'script' => $script,
      'curl' => $curl,
    ];
  }
}
