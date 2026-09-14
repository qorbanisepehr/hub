<?php

return [
    'deleted' => 'Employee deleted successfully.',
    'not_found' => 'Employee not found.',
    'saved' => 'Employee profile saved.',
    'submitted' => 'Employee profile submitted successfully.',
    'sections' => [
        'personal_info' => 'Personal Information',
        'contact_info' => 'Contact Information',
        'employment' => 'Employment Information',
        'education' => 'Education',
        'work_experience' => 'Work Experience',
        'skills' => 'Skills',
        'training' => 'Training & Courses',
        'additional_info' => 'Additional Information',
        'social_insurance' => 'Social Insurance',
        'contracts' => 'Contracts',
        'financial' => 'Financial Information',
        'supplementary_insurance' => 'Supplementary Insurance',
        'dependents' => 'Dependents',
        'document_inquiries' => 'Document Inquiries',
    ],
    'dependents' => [
        'fields' => [
            'relationship_type' => 'Relationship type',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'id_number' => 'National ID',
            'gender' => 'Gender',
            'birth_date' => 'Birth date',
            'custom_relationship' => 'Relationship (other)',
            'marriage_date' => 'Marriage date',
        ],
        'validation' => [
            'birth_date_not_future' => 'The dependent birth date cannot be in the future.',
        ],
        'field_label' => 'Dependent :n',
    ],
    'social_insurance' => [
        'fields' => [
            'start_date' => 'Start date',
            'end_date' => 'End date',
        ],
        'validation' => [
            'end_date_before_start_date' => 'The end date must not be earlier than the start date.',
        ],
    ],
    'contracts' => [
        'field_label' => 'Contract :n',
        'field_label_range' => 'Contract from :start to :end',
        'field_label_from' => 'Contract from :start',
    ],
    'document_inquiries' => [
        'field_labels' => [
            'education_degree' => 'Education degree inquiry :n',
            'criminal-record' => 'Criminal record inquiry',
            'social-insurance' => 'Social insurance inquiry',
            'sana-verification' => 'Sana verification inquiry',
        ],
        'validation' => [
            'invalid_education_index' => 'Invalid education record reference for an inquiry.',
        ],
    ],
    'exports' => [
        'fields' => [
            'employment.personnel_code' => 'Personnel code',
            'employment.employment_type' => 'Employment type',
            'employment.employment_status' => 'Employment status',
            'employment.hire_date' => 'Hire date',
        ],
    ],
    'documents' => [
        'max_files_reached' => 'The maximum of :count items for this document type has been reached.',
        'total_max_files_reached' => 'The maximum of :count files for this employee has been reached.',
        'trashed' => 'Document moved to trash.',
        'restored' => 'Document restored.',
    ],
    'validation' => [
        'personnel_code_unique' => 'This personnel code is already assigned to another employee.',
    ],
];
