<?php

declare(strict_types=1);

/**
 * Application route map.
 */

return [
    'GET' => [
        '/' => [
            'handler' => 'public.home',
            'layout' => 'public',
            'title' => 'Welcome',
        ],
        '/health' => [
            'handler' => 'health',
            'layout' => null,
        ],
        '/status' => [
            'handler' => 'health',
            'layout' => null,
        ],
        '/login' => [
            'handler' => 'auth.login',
            'layout' => 'auth',
            'title' => 'Sign In',
            'middleware' => ['guest'],
        ],
        '/forgot-password' => [
            'handler' => 'auth.forgot-password',
            'layout' => 'auth',
            'title' => 'Forgot Password',
            'middleware' => ['guest'],
        ],
        '/reset-password/{token}' => [
            'handler' => 'auth.reset-password',
            'layout' => 'auth',
            'title' => 'Reset Password',
            'middleware' => ['guest'],
        ],
        '/invite/{token}' => [
            'handler' => 'auth.invite',
            'layout' => null,
        ],
        '/admin' => [
            'handler' => 'admin.dashboard',
            'layout' => 'admin',
            'title' => 'Dashboard',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/forms' => [
            'handler' => 'admin.forms.index',
            'layout' => 'admin',
            'title' => 'Forms',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/forms/new' => [
            'handler' => 'admin.forms.create',
            'layout' => 'admin',
            'title' => 'New Form',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/{id}/edit' => [
            'handler' => 'admin.forms.edit',
            'layout' => 'admin',
            'title' => 'Edit Form',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/{formId}/submissions' => [
            'handler' => 'admin.submissions.index',
            'layout' => 'admin',
            'title' => 'Submissions',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/forms/{formId}/submissions/{id}' => [
            'handler' => 'admin.submissions.show',
            'layout' => 'admin',
            'title' => 'Submission',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/forms/{formId}/submissions/export' => [
            'action' => 'SubmissionController@export',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/forms/{formId}/analytics' => [
            'handler' => 'admin.forms.analytics',
            'layout' => 'admin',
            'title' => 'Analytics',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/templates' => [
            'handler' => 'admin.templates.index',
            'layout' => 'admin',
            'title' => 'Templates',
            'middleware' => ['auth', 'role:viewer'],
        ],
        '/admin/settings' => [
            'handler' => 'admin.settings.index',
            'layout' => 'admin',
            'title' => 'Settings',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/backup/sql' => [
            'action' => 'SettingsController@exportSql',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/backup/json' => [
            'action' => 'SettingsController@exportJson',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/submissions/{submissionId}/files/{fileId}' => [
            'action' => 'SubmissionFileController@download',
            'middleware' => ['auth', 'role:viewer'],
        ],
    ],
    'OPTIONS' => [
        '/submit/{slug}' => [
            'action' => 'SubmitController@options',
        ],
    ],
    'POST' => [
        '/login' => [
            'action' => 'AuthController@login',
            'middleware' => ['guest'],
        ],
        '/logout' => [
            'action' => 'AuthController@logout',
            'middleware' => ['auth'],
        ],
        '/forgot-password' => [
            'action' => 'AuthController@forgotPassword',
            'middleware' => ['guest'],
        ],
        '/reset-password' => [
            'action' => 'AuthController@resetPassword',
            'middleware' => ['guest'],
        ],
        '/admin/forms' => [
            'action' => 'FormController@store',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/update' => [
            'action' => 'FormController@update',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/duplicate' => [
            'action' => 'FormController@duplicate',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/delete' => [
            'action' => 'FormController@delete',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/toggle' => [
            'action' => 'FormController@toggle',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/forms/submissions/bulk' => [
            'action' => 'SubmissionController@bulk',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/submit/{slug}' => [
            'action' => 'SubmitController@submit',
        ],
        '/admin/templates/use' => [
            'action' => 'FormController@useTemplate',
            'middleware' => ['auth', 'role:editor'],
        ],
        '/admin/settings/general' => [
            'action' => 'SettingsController@saveGeneral',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/smtp' => [
            'action' => 'SettingsController@saveSmtp',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/smtp/test' => [
            'action' => 'SettingsController@testSmtp',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/security' => [
            'action' => 'SettingsController@saveSecurity',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/api-keys/generate' => [
            'action' => 'SettingsController@generateApiKey',
            'middleware' => ['auth', 'role:admin'],
        ],
        '/admin/settings/api-keys/revoke' => [
            'action' => 'SettingsController@revokeApiKey',
            'middleware' => ['auth', 'role:admin'],
        ],
    ],
];
