<?php

/*
|--------------------------------------------------------------------------
| Permission & Role catalogue (server-defined)
|--------------------------------------------------------------------------
| Canonical source of truth for the RBAC layer. Permission names are ONLY
| ever defined here — never accepted from the client. Later feature modules
| may append to `groups`. The `roles` matrix seeds sensible starting points;
| fine-grained assignment is done at runtime via the role-management API.
*/

return [
    // All roles/permissions belong to the stateless API (JWT) guard.
    'guard' => 'api',

    // Roles that ship with the system: cannot be deleted, and Admin cannot
    // be renamed or have its permissions changed (it always holds all).
    'system_roles' => [
        'Admin', 'Principal', 'Teacher', 'Accountant', 'Receptionist',
        'Librarian', 'Transport Manager', 'HR Manager', 'Staff', 'Parent', 'Student',
    ],

    // Permissions grouped by domain.
    'groups' => [
        'dashboard' => ['dashboard.view'],
        'user' => ['user.view', 'user.create', 'user.edit', 'user.delete'],
        'role' => ['role.view', 'role.create', 'role.edit', 'role.delete'],
        'permission' => ['permission.view'],
        'settings' => ['settings.view', 'settings.manage'],
        'academic_year' => ['academic_year.view', 'academic_year.create', 'academic_year.edit', 'academic_year.delete'],
        'class' => ['class.view', 'class.create', 'class.edit', 'class.delete'],
        'section' => ['section.view', 'section.create', 'section.edit', 'section.delete'],
        'subject' => ['subject.view', 'subject.create', 'subject.edit', 'subject.delete'],
        'guardian' => ['guardian.view', 'guardian.create', 'guardian.edit', 'guardian.delete'],
        'student' => ['student.view', 'student.create', 'student.edit', 'student.delete', 'student.export'],
        'teacher' => ['teacher.view', 'teacher.create', 'teacher.edit', 'teacher.delete'],
        'enrollment' => ['enrollment.view', 'enrollment.create', 'enrollment.edit', 'enrollment.delete'],
        'attendance' => ['attendance.view', 'attendance.create', 'attendance.edit'],
        'routine' => ['routine.view', 'routine.create', 'routine.edit'],
        'exam' => ['exam.view', 'exam.create', 'exam.edit', 'exam.delete', 'exam.marks_entry'],
        'result' => ['result.view', 'result.publish'],
        'fee' => ['fee.view', 'fee.create', 'fee.payment', 'fee.refund', 'fee.report'],
        'accounts' => ['accounts.view', 'accounts.create', 'accounts.report'],
        'report' => ['report.view'],
        'notification' => ['notification.view', 'notification.manage'],
        'audit' => ['audit.view'],
        'admission' => ['admission.view', 'admission.manage'],
        'website' => ['website.view', 'website.manage', 'website.pages', 'website.notices', 'website.events', 'website.gallery', 'website.slider', 'website.theme'],
    ],

    /*
    | Role => permission assignment. '*' means every permission. These are
    | starting points and can be edited via PUT /api/roles/{id}/permissions.
    */
    'roles' => [
        'Admin' => '*',

        'Principal' => [
            'dashboard.view', 'user.view', 'settings.view',
            'academic_year.view', 'class.view', 'section.view', 'subject.view',
            'guardian.view', 'student.view', 'teacher.view', 'enrollment.view',
            'attendance.view', 'routine.view', 'exam.view',
            'result.view', 'result.publish',
            'fee.view', 'fee.report', 'accounts.view', 'accounts.report',
            'report.view', 'notification.view', 'audit.view', 'admission.view', 'website.view',
        ],

        'Teacher' => [
            'dashboard.view', 'student.view', 'subject.view', 'routine.view',
            'attendance.view', 'attendance.create', 'attendance.edit',
            'exam.view', 'exam.marks_entry', 'result.view', 'notification.view',
        ],

        'Accountant' => [
            'dashboard.view', 'student.view',
            'fee.view', 'fee.create', 'fee.payment', 'fee.refund', 'fee.report',
            'accounts.view', 'accounts.create', 'accounts.report',
            'report.view', 'notification.view',
        ],

        'Receptionist' => [
            'dashboard.view', 'student.view', 'student.create',
            'guardian.view', 'guardian.create',
            'admission.view', 'admission.manage', 'attendance.view', 'notification.view',
        ],

        'Librarian' => ['dashboard.view', 'student.view'],

        'Transport Manager' => ['dashboard.view', 'student.view'],

        'HR Manager' => [
            'dashboard.view', 'teacher.view', 'teacher.create', 'teacher.edit', 'teacher.delete',
            'user.view', 'report.view',
        ],

        'Staff' => ['dashboard.view'],

        'Parent' => ['dashboard.view'],

        'Student' => ['dashboard.view'],
    ],
];
