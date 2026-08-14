<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Default form field types and settings structures (PRD §5.4).
 */
class FormDefaults
{
  /** @var list<string> */
  public const FIELD_TYPES = [
    'text', 'textarea', 'email', 'number', 'phone', 'url',
    'select', 'radio', 'checkbox', 'single-checkbox',
    'date', 'time', 'file', 'hidden', 'heading', 'paragraph',
  ];

  /** @var list<string> */
  public const INPUT_TYPES = [
    'text', 'textarea', 'email', 'number', 'phone', 'url',
    'select', 'radio', 'checkbox', 'single-checkbox',
    'date', 'time', 'file', 'hidden',
  ];

  /**
   * @return array<string, mixed>
   */
  public static function field(string $type = 'text'): array
  {
    return [
      'id' => 'f_' . bin2hex(random_bytes(4)),
      'type' => $type,
      'label' => ucfirst(str_replace('-', ' ', $type)),
      'placeholder' => '',
      'required' => !in_array($type, ['heading', 'paragraph', 'hidden'], true),
      'validation' => [
        'min_length' => 0,
        'max_length' => 0,
        'regex' => '',
        'error_message' => '',
      ],
      'default' => '',
      'options' => in_array($type, ['select', 'radio', 'checkbox'], true)
        ? [['label' => 'Option 1', 'value' => 'option_1']]
        : [],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public static function settings(): array
  {
    return [
      'success' => [
        'type' => 'message',
        'message' => 'Thank you for your submission!',
        'redirect_url' => '',
      ],
      'notifications' => [
        'recipients' => [],
        'subject' => 'New form submission',
        'auto_reply' => false,
      ],
      'spam' => [
        'honeypot' => true,
        'recaptcha' => false,
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'recaptcha_fail_mode' => 'closed',
      ],
      'rate_limit' => [
        'per_minute' => 10,
      ],
      'uploads' => [
        'max_bytes' => 5242880,
      ],
      'allowed_domains' => [],
      'webhook_url' => '',
    ];
  }
}
