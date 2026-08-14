<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Bundled form template definitions (PRD §5.7).
 */
class TemplateDefinitions
{
  /**
   * @return list<array<string, mixed>>
   */
  public static function all(): array
  {
    $defaults = FormDefaults::settings();

    return [
      [
        'slug' => 'contact-us',
        'name' => 'Contact Us',
        'description' => 'General contact form with name, email, and message.',
        'category' => 'general',
        'sort_order' => 1,
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, 'Jane Doe'),
          self::field('f_email', 'email', 'Email', true, 'you@example.com'),
          self::field('f_phone', 'phone', 'Phone', false, ''),
          self::field('f_message', 'textarea', 'Message', true, 'How can we help?'),
        ],
        'settings' => $defaults,
      ],
      [
        'slug' => 'newsletter-signup',
        'name' => 'Newsletter Signup',
        'description' => 'Collect email subscribers with optional name.',
        'category' => 'marketing',
        'sort_order' => 2,
        'fields' => [
          self::field('f_email', 'email', 'Email Address', true, 'you@example.com'),
          self::field('f_name', 'text', 'First Name', false, ''),
        ],
        'settings' => array_merge($defaults, [
          'success' => ['type' => 'message', 'message' => 'Thanks for subscribing!', 'redirect_url' => ''],
        ]),
      ],
      [
        'slug' => 'job-application',
        'name' => 'Job Application',
        'description' => 'Collect applicant details, resume, and cover letter.',
        'category' => 'hr',
        'sort_order' => 3,
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_position', 'text', 'Position Applied For', true, ''),
          self::field('f_resume', 'file', 'Resume (PDF)', true, ''),
          self::field('f_cover', 'textarea', 'Cover Letter', false, ''),
        ],
        'settings' => $defaults,
      ],
      [
        'slug' => 'event-rsvp',
        'name' => 'Event RSVP',
        'description' => 'RSVP form with guest count and dietary preferences.',
        'category' => 'events',
        'sort_order' => 4,
        'fields' => [
          self::field('f_name', 'text', 'Your Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_guests', 'number', 'Number of Guests', true, ''),
          self::field('f_attending', 'radio', 'Will you attend?', true, '', [
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
            ['label' => 'Maybe', 'value' => 'maybe'],
          ]),
          self::field('f_dietary', 'textarea', 'Dietary Requirements', false, ''),
        ],
        'settings' => $defaults,
      ],
      [
        'slug' => 'feedback-nps',
        'name' => 'Feedback / NPS',
        'description' => 'Net Promoter Score and open feedback.',
        'category' => 'feedback',
        'sort_order' => 5,
        'fields' => [
          self::field('f_nps', 'select', 'How likely are you to recommend us? (0–10)', true, '', array_map(
            fn (int $n) => ['label' => (string) $n, 'value' => (string) $n],
            range(0, 10)
          )),
          self::field('f_feedback', 'textarea', 'What is the primary reason for your score?', true, ''),
          self::field('f_email', 'email', 'Email (optional)', false, ''),
        ],
        'settings' => $defaults,
      ],
      [
        'slug' => 'booking-request',
        'name' => 'Booking Request',
        'description' => 'Appointment or reservation request with date and time.',
        'category' => 'booking',
        'sort_order' => 6,
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_date', 'date', 'Preferred Date', true, ''),
          self::field('f_time', 'time', 'Preferred Time', true, ''),
          self::field('f_service', 'select', 'Service', true, '', [
            ['label' => 'Consultation', 'value' => 'consultation'],
            ['label' => 'Follow-up', 'value' => 'followup'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_notes', 'textarea', 'Additional Notes', false, ''),
        ],
        'settings' => $defaults,
      ],
      [
        'slug' => 'support-ticket',
        'name' => 'Support Ticket',
        'description' => 'Customer support request with priority and category.',
        'category' => 'support',
        'sort_order' => 7,
        'fields' => [
          self::field('f_name', 'text', 'Your Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_subject', 'text', 'Subject', true, ''),
          self::field('f_category', 'select', 'Category', true, '', [
            ['label' => 'Billing', 'value' => 'billing'],
            ['label' => 'Technical', 'value' => 'technical'],
            ['label' => 'Account', 'value' => 'account'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_priority', 'radio', 'Priority', true, '', [
            ['label' => 'Low', 'value' => 'low'],
            ['label' => 'Medium', 'value' => 'medium'],
            ['label' => 'High', 'value' => 'high'],
          ]),
          self::field('f_description', 'textarea', 'Describe your issue', true, ''),
          self::field('f_attachment', 'file', 'Screenshot (optional)', false, ''),
        ],
        'settings' => $defaults,
      ],
    ];
  }

  /**
   * @param list<array{label: string, value: string}> $options
   * @return array<string, mixed>
   */
  private static function field(
    string $id,
    string $type,
    string $label,
    bool $required,
    string $placeholder = '',
    array $options = []
  ): array {
    return [
      'id' => $id,
      'type' => $type,
      'label' => $label,
      'placeholder' => $placeholder,
      'required' => $required,
      'validation' => ['min_length' => 0, 'max_length' => 0, 'regex' => '', 'error_message' => ''],
      'default' => '',
      'options' => $options,
    ];
  }
}
