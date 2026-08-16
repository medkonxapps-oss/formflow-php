<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Bundled form template definitions — 30 industry starter forms.
 */
class TemplateDefinitions
{
  /**
   * @return list<array<string, mixed>>
   */
  public static function all(): array
  {
    $defaults = FormDefaults::settings();
    $i = 0;
    $t = static function (array $row) use ($defaults, &$i): array {
      $i++;
      $row['sort_order'] = $i;
      $row['settings'] = $row['settings'] ?? $defaults;

      return $row;
    };

    return [
      $t([
        'slug' => 'contact-us',
        'name' => 'Contact Us',
        'description' => 'General contact form with name, email, and message.',
        'category' => 'general',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, 'Jane Doe'),
          self::field('f_email', 'email', 'Email', true, 'you@example.com'),
          self::field('f_phone', 'phone', 'Phone', false, ''),
          self::field('f_message', 'textarea', 'Message', true, 'How can we help?'),
        ],
      ]),
      $t([
        'slug' => 'quote-request',
        'name' => 'Quote Request',
        'description' => 'Let customers request a price quote for a product or service.',
        'category' => 'general',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_company', 'text', 'Company', false, ''),
          self::field('f_service', 'select', 'What do you need?', true, '', [
            ['label' => 'Product', 'value' => 'product'],
            ['label' => 'Service', 'value' => 'service'],
            ['label' => 'Custom project', 'value' => 'custom'],
          ]),
          self::field('f_budget', 'select', 'Budget range', false, '', [
            ['label' => 'Under $1,000', 'value' => 'under_1k'],
            ['label' => '$1,000 – $5,000', 'value' => '1k_5k'],
            ['label' => '$5,000+', 'value' => '5k_plus'],
          ]),
          self::field('f_details', 'textarea', 'Project details', true, ''),
        ],
      ]),
      $t([
        'slug' => 'customer-complaint',
        'name' => 'Customer Complaint',
        'description' => 'Log a complaint with order details and preferred resolution.',
        'category' => 'general',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_order', 'text', 'Order / invoice number', false, ''),
          self::field('f_issue', 'select', 'Issue type', true, '', [
            ['label' => 'Product quality', 'value' => 'quality'],
            ['label' => 'Delivery', 'value' => 'delivery'],
            ['label' => 'Billing', 'value' => 'billing'],
            ['label' => 'Staff / service', 'value' => 'service'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_details', 'textarea', 'What happened?', true, ''),
          self::field('f_resolution', 'textarea', 'How can we make this right?', false, ''),
        ],
      ]),
      $t([
        'slug' => 'newsletter-signup',
        'name' => 'Newsletter Signup',
        'description' => 'Collect email subscribers with optional name.',
        'category' => 'marketing',
        'fields' => [
          self::field('f_email', 'email', 'Email Address', true, 'you@example.com'),
          self::field('f_name', 'text', 'First Name', false, ''),
        ],
        'settings' => array_merge($defaults, [
          'success' => ['type' => 'message', 'message' => 'Thanks for subscribing!', 'redirect_url' => ''],
        ]),
      ]),
      $t([
        'slug' => 'lead-generation',
        'name' => 'Lead Generation',
        'description' => 'Capture potential customer information and interests.',
        'category' => 'marketing',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, 'John Doe'),
          self::field('f_email', 'email', 'Work Email', true, 'john@company.com'),
          self::field('f_company', 'text', 'Company Name', false, ''),
          self::field('f_interest', 'select', 'What are you interested in?', true, '', [
            ['label' => 'Product Demo', 'value' => 'demo'],
            ['label' => 'Pricing Information', 'value' => 'pricing'],
            ['label' => 'Partnership', 'value' => 'partnership'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'webinar-signup',
        'name' => 'Webinar Signup',
        'description' => 'Register attendees for a webinar or online event.',
        'category' => 'marketing',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_company', 'text', 'Company', false, ''),
          self::field('f_role', 'text', 'Job title', false, ''),
          self::field('f_session', 'select', 'Session', true, '', [
            ['label' => 'Morning', 'value' => 'morning'],
            ['label' => 'Afternoon', 'value' => 'afternoon'],
            ['label' => 'Evening', 'value' => 'evening'],
          ]),
          self::field('f_questions', 'textarea', 'Questions for the speaker', false, ''),
        ],
      ]),
      $t([
        'slug' => 'job-application',
        'name' => 'Job Application',
        'description' => 'Collect applicant details, resume, and cover letter.',
        'category' => 'hr',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_position', 'text', 'Position Applied For', true, ''),
          self::field('f_resume', 'file', 'Resume (PDF)', true, ''),
          self::field('f_cover', 'textarea', 'Cover Letter', false, ''),
        ],
      ]),
      $t([
        'slug' => 'employee-onboarding',
        'name' => 'Employee Onboarding',
        'description' => 'Collect new-hire details before day one.',
        'category' => 'hr',
        'fields' => [
          self::field('f_name', 'text', 'Full legal name', true, ''),
          self::field('f_email', 'email', 'Personal email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_start', 'date', 'Start date', true, ''),
          self::field('f_department', 'select', 'Department', true, '', [
            ['label' => 'Operations', 'value' => 'operations'],
            ['label' => 'Sales', 'value' => 'sales'],
            ['label' => 'Engineering', 'value' => 'engineering'],
            ['label' => 'HR', 'value' => 'hr'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_emergency', 'text', 'Emergency contact', true, ''),
          self::field('f_equipment', 'checkbox', 'Equipment needed', false, '', [
            ['label' => 'Laptop', 'value' => 'laptop'],
            ['label' => 'Phone', 'value' => 'phone'],
            ['label' => 'Access badge', 'value' => 'badge'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'leave-request',
        'name' => 'Leave Request',
        'description' => 'Staff time-off request with dates and type of leave.',
        'category' => 'hr',
        'fields' => [
          self::field('f_name', 'text', 'Employee name', true, ''),
          self::field('f_email', 'email', 'Work email', true, ''),
          self::field('f_type', 'select', 'Leave type', true, '', [
            ['label' => 'Vacation', 'value' => 'vacation'],
            ['label' => 'Sick', 'value' => 'sick'],
            ['label' => 'Personal', 'value' => 'personal'],
            ['label' => 'Unpaid', 'value' => 'unpaid'],
          ]),
          self::field('f_from', 'date', 'From', true, ''),
          self::field('f_to', 'date', 'To', true, ''),
          self::field('f_reason', 'textarea', 'Reason (optional)', false, ''),
        ],
      ]),
      $t([
        'slug' => 'event-rsvp',
        'name' => 'Event RSVP',
        'description' => 'RSVP form with guest count and dietary preferences.',
        'category' => 'events',
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
      ]),
      $t([
        'slug' => 'volunteer-signup',
        'name' => 'Volunteer Signup',
        'description' => 'Sign up volunteers with availability and skills.',
        'category' => 'events',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_role', 'select', 'Preferred role', true, '', [
            ['label' => 'Registration desk', 'value' => 'registration'],
            ['label' => 'Setup / teardown', 'value' => 'setup'],
            ['label' => 'Guest hospitality', 'value' => 'hospitality'],
            ['label' => 'Wherever needed', 'value' => 'any'],
          ]),
          self::field('f_days', 'checkbox', 'Available days', true, '', [
            ['label' => 'Friday', 'value' => 'fri'],
            ['label' => 'Saturday', 'value' => 'sat'],
            ['label' => 'Sunday', 'value' => 'sun'],
          ]),
          self::field('f_notes', 'textarea', 'Notes', false, ''),
        ],
      ]),
      $t([
        'slug' => 'feedback-nps',
        'name' => 'Feedback / NPS',
        'description' => 'Net Promoter Score and open feedback.',
        'category' => 'feedback',
        'fields' => [
          self::field('f_nps', 'select', 'How likely are you to recommend us? (0–10)', true, '', array_map(
            fn (int $n) => ['label' => (string) $n, 'value' => (string) $n],
            range(0, 10)
          )),
          self::field('f_feedback', 'textarea', 'What is the primary reason for your score?', true, ''),
          self::field('f_email', 'email', 'Email (optional)', false, ''),
        ],
      ]),
      $t([
        'slug' => 'booking-request',
        'name' => 'Booking Request',
        'description' => 'Appointment or reservation request with date and time.',
        'category' => 'booking',
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
      ]),
      $t([
        'slug' => 'support-ticket',
        'name' => 'Support Ticket',
        'description' => 'Customer support request with priority and category.',
        'category' => 'support',
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
      ]),
      $t([
        'slug' => 'course-registration',
        'name' => 'Course Registration',
        'description' => 'Enroll students in courses or workshops.',
        'category' => 'education',
        'fields' => [
          self::field('f_student', 'text', 'Student Name', true, ''),
          self::field('f_email', 'email', 'Email Address', true, ''),
          self::field('f_course', 'select', 'Select Course', true, '', [
            ['label' => 'Web Development Bootcamp', 'value' => 'web_dev'],
            ['label' => 'Data Science 101', 'value' => 'data_science'],
            ['label' => 'UI/UX Design', 'value' => 'ui_ux'],
          ]),
          self::field('f_level', 'radio', 'Experience Level', true, '', [
            ['label' => 'Beginner', 'value' => 'beginner'],
            ['label' => 'Intermediate', 'value' => 'intermediate'],
            ['label' => 'Advanced', 'value' => 'advanced'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'student-admission',
        'name' => 'Student Admission',
        'description' => 'School or college admission enquiry with program choice.',
        'category' => 'education',
        'fields' => [
          self::field('f_student', 'text', 'Student name', true, ''),
          self::field('f_parent', 'text', 'Parent / guardian name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_grade', 'select', 'Applying for', true, '', [
            ['label' => 'Primary', 'value' => 'primary'],
            ['label' => 'Secondary', 'value' => 'secondary'],
            ['label' => 'Undergraduate', 'value' => 'undergrad'],
            ['label' => 'Postgraduate', 'value' => 'postgrad'],
          ]),
          self::field('f_year', 'text', 'Academic year', false, '2026–27'),
          self::field('f_notes', 'textarea', 'Additional information', false, ''),
        ],
      ]),
      $t([
        'slug' => 'parent-meeting',
        'name' => 'Parent–Teacher Meeting',
        'description' => 'Book a meeting slot with a teacher or counselor.',
        'category' => 'education',
        'fields' => [
          self::field('f_parent', 'text', 'Parent name', true, ''),
          self::field('f_student', 'text', 'Student name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_teacher', 'text', 'Teacher / class', true, ''),
          self::field('f_date', 'date', 'Preferred date', true, ''),
          self::field('f_time', 'select', 'Preferred time', true, '', [
            ['label' => '9:00 AM', 'value' => '09:00'],
            ['label' => '11:00 AM', 'value' => '11:00'],
            ['label' => '2:00 PM', 'value' => '14:00'],
            ['label' => '4:00 PM', 'value' => '16:00'],
          ]),
          self::field('f_topic', 'textarea', 'Topics to discuss', false, ''),
        ],
      ]),
      $t([
        'slug' => 'medical-intake',
        'name' => 'Medical Intake',
        'description' => 'Collect basic patient medical history securely.',
        'category' => 'health',
        'fields' => [
          self::field('f_name', 'text', 'Patient Name', true, ''),
          self::field('f_dob', 'date', 'Date of Birth', true, ''),
          self::field('f_phone', 'phone', 'Contact Number', true, ''),
          self::field('f_allergies', 'textarea', 'Known Allergies (if any)', false, ''),
          self::field('f_medications', 'textarea', 'Current Medications', false, ''),
          self::field('f_emergency', 'text', 'Emergency Contact Name & Phone', true, ''),
        ],
      ]),
      $t([
        'slug' => 'clinic-appointment',
        'name' => 'Clinic Appointment',
        'description' => 'Request a doctor or clinic visit with reason for visit.',
        'category' => 'health',
        'fields' => [
          self::field('f_name', 'text', 'Patient name', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_email', 'email', 'Email', false, ''),
          self::field('f_dept', 'select', 'Department', true, '', [
            ['label' => 'General physician', 'value' => 'gp'],
            ['label' => 'Dental', 'value' => 'dental'],
            ['label' => 'Pediatrics', 'value' => 'pediatrics'],
            ['label' => 'Dermatology', 'value' => 'dermatology'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_date', 'date', 'Preferred date', true, ''),
          self::field('f_reason', 'textarea', 'Reason for visit', true, ''),
          self::field('f_new', 'radio', 'Patient type', true, '', [
            ['label' => 'New patient', 'value' => 'new'],
            ['label' => 'Returning', 'value' => 'returning'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'property-inquiry',
        'name' => 'Property Inquiry',
        'description' => 'Buyer or renter enquiry for a listing.',
        'category' => 'real-estate',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_type', 'select', 'I am looking to', true, '', [
            ['label' => 'Buy', 'value' => 'buy'],
            ['label' => 'Rent', 'value' => 'rent'],
            ['label' => 'Sell', 'value' => 'sell'],
          ]),
          self::field('f_property', 'text', 'Property / listing ID', false, ''),
          self::field('f_budget', 'text', 'Budget', false, ''),
          self::field('f_message', 'textarea', 'Message', true, ''),
        ],
      ]),
      $t([
        'slug' => 'home-viewing',
        'name' => 'Home Viewing Request',
        'description' => 'Schedule a property viewing with preferred date and time.',
        'category' => 'real-estate',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_address', 'text', 'Property address or listing', true, ''),
          self::field('f_date', 'date', 'Preferred date', true, ''),
          self::field('f_time', 'time', 'Preferred time', true, ''),
          self::field('f_party', 'number', 'Number of visitors', false, ''),
        ],
      ]),
      $t([
        'slug' => 'rental-application',
        'name' => 'Rental Application',
        'description' => 'Tenant application with occupancy and employment details.',
        'category' => 'real-estate',
        'fields' => [
          self::field('f_name', 'text', 'Applicant name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_occupants', 'number', 'Number of occupants', true, ''),
          self::field('f_movein', 'date', 'Desired move-in date', true, ''),
          self::field('f_income', 'text', 'Monthly income', false, ''),
          self::field('f_employer', 'text', 'Employer', false, ''),
          self::field('f_pets', 'radio', 'Pets?', true, '', [
            ['label' => 'No', 'value' => 'no'],
            ['label' => 'Yes', 'value' => 'yes'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'table-reservation',
        'name' => 'Table Reservation',
        'description' => 'Restaurant booking with party size, date, and time.',
        'category' => 'restaurant',
        'fields' => [
          self::field('f_name', 'text', 'Name', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_email', 'email', 'Email', false, ''),
          self::field('f_date', 'date', 'Date', true, ''),
          self::field('f_time', 'time', 'Time', true, ''),
          self::field('f_guests', 'number', 'Number of guests', true, ''),
          self::field('f_occasion', 'select', 'Occasion', false, '', [
            ['label' => 'None', 'value' => 'none'],
            ['label' => 'Birthday', 'value' => 'birthday'],
            ['label' => 'Anniversary', 'value' => 'anniversary'],
            ['label' => 'Business', 'value' => 'business'],
          ]),
          self::field('f_notes', 'textarea', 'Special requests', false, ''),
        ],
      ]),
      $t([
        'slug' => 'catering-order',
        'name' => 'Catering Order',
        'description' => 'Catering request for events with guest count and menu notes.',
        'category' => 'restaurant',
        'fields' => [
          self::field('f_name', 'text', 'Contact name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_event_date', 'date', 'Event date', true, ''),
          self::field('f_guests', 'number', 'Guest count', true, ''),
          self::field('f_venue', 'text', 'Venue / address', true, ''),
          self::field('f_menu', 'select', 'Menu style', true, '', [
            ['label' => 'Buffet', 'value' => 'buffet'],
            ['label' => 'Plated', 'value' => 'plated'],
            ['label' => 'Boxed meals', 'value' => 'boxed'],
            ['label' => 'Custom', 'value' => 'custom'],
          ]),
          self::field('f_notes', 'textarea', 'Dietary / menu notes', false, ''),
        ],
      ]),
      $t([
        'slug' => 'legal-consultation',
        'name' => 'Legal Consultation',
        'description' => 'Request a lawyer consultation with matter type.',
        'category' => 'legal',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_area', 'select', 'Practice area', true, '', [
            ['label' => 'Family', 'value' => 'family'],
            ['label' => 'Business / contracts', 'value' => 'business'],
            ['label' => 'Property', 'value' => 'property'],
            ['label' => 'Immigration', 'value' => 'immigration'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_urgency', 'radio', 'Urgency', true, '', [
            ['label' => 'This week', 'value' => 'week'],
            ['label' => 'This month', 'value' => 'month'],
            ['label' => 'No rush', 'value' => 'later'],
          ]),
          self::field('f_summary', 'textarea', 'Brief summary (do not share secrets)', true, ''),
        ],
      ]),
      $t([
        'slug' => 'client-intake',
        'name' => 'Client Intake',
        'description' => 'New client intake for a law firm or consultancy.',
        'category' => 'legal',
        'fields' => [
          self::field('f_name', 'text', 'Client name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_org', 'text', 'Company / organization', false, ''),
          self::field('f_referred', 'text', 'How did you hear about us?', false, ''),
          self::field('f_conflict', 'textarea', 'Other parties involved (conflict check)', false, ''),
          self::field('f_goals', 'textarea', 'What outcome are you seeking?', true, ''),
        ],
      ]),
      $t([
        'slug' => 'car-service',
        'name' => 'Vehicle Service Booking',
        'description' => 'Book garage or dealership service with vehicle details.',
        'category' => 'automotive',
        'fields' => [
          self::field('f_name', 'text', 'Name', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_email', 'email', 'Email', false, ''),
          self::field('f_make', 'text', 'Make & model', true, ''),
          self::field('f_year', 'text', 'Year', false, ''),
          self::field('f_reg', 'text', 'Registration number', true, ''),
          self::field('f_service', 'select', 'Service type', true, '', [
            ['label' => 'Oil change', 'value' => 'oil'],
            ['label' => 'General service', 'value' => 'general'],
            ['label' => 'Brakes / tires', 'value' => 'brakes'],
            ['label' => 'Diagnostics', 'value' => 'diagnostics'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_date', 'date', 'Preferred date', true, ''),
          self::field('f_notes', 'textarea', 'Describe the issue', false, ''),
        ],
      ]),
      $t([
        'slug' => 'test-drive',
        'name' => 'Test Drive Request',
        'description' => 'Schedule a test drive at a dealership.',
        'category' => 'automotive',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_vehicle', 'text', 'Vehicle of interest', true, ''),
          self::field('f_date', 'date', 'Preferred date', true, ''),
          self::field('f_time', 'time', 'Preferred time', true, ''),
          self::field('f_license', 'radio', 'Valid driving license?', true, '', [
            ['label' => 'Yes', 'value' => 'yes'],
            ['label' => 'No', 'value' => 'no'],
          ]),
        ],
      ]),
      $t([
        'slug' => 'product-return',
        'name' => 'Product Return',
        'description' => 'E-commerce return or exchange request.',
        'category' => 'retail',
        'fields' => [
          self::field('f_name', 'text', 'Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_order', 'text', 'Order number', true, ''),
          self::field('f_item', 'text', 'Product name / SKU', true, ''),
          self::field('f_reason', 'select', 'Reason', true, '', [
            ['label' => 'Wrong size', 'value' => 'size'],
            ['label' => 'Damaged', 'value' => 'damaged'],
            ['label' => 'Not as described', 'value' => 'description'],
            ['label' => 'Changed mind', 'value' => 'changed'],
            ['label' => 'Other', 'value' => 'other'],
          ]),
          self::field('f_action', 'radio', 'I want a', true, '', [
            ['label' => 'Refund', 'value' => 'refund'],
            ['label' => 'Exchange', 'value' => 'exchange'],
          ]),
          self::field('f_notes', 'textarea', 'More details', false, ''),
        ],
      ]),
      $t([
        'slug' => 'membership-signup',
        'name' => 'Gym Membership',
        'description' => 'Fitness club membership enquiry with plan choice.',
        'category' => 'fitness',
        'fields' => [
          self::field('f_name', 'text', 'Full Name', true, ''),
          self::field('f_email', 'email', 'Email', true, ''),
          self::field('f_phone', 'phone', 'Phone', true, ''),
          self::field('f_plan', 'select', 'Plan', true, '', [
            ['label' => 'Monthly', 'value' => 'monthly'],
            ['label' => 'Quarterly', 'value' => 'quarterly'],
            ['label' => 'Annual', 'value' => 'annual'],
            ['label' => 'Day pass', 'value' => 'day'],
          ]),
          self::field('f_goals', 'checkbox', 'Goals', false, '', [
            ['label' => 'Weight loss', 'value' => 'weight'],
            ['label' => 'Strength', 'value' => 'strength'],
            ['label' => 'Cardio', 'value' => 'cardio'],
            ['label' => 'Classes', 'value' => 'classes'],
          ]),
          self::field('f_start', 'date', 'Preferred start date', false, ''),
        ],
      ]),
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
