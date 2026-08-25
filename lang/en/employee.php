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
        ],
        'validation' => [
            'birth_date_not_future' => 'The dependent birth date cannot be in the future.',
        ],
        'field_label' => 'Dependent :n',
    ],
    'document_inquiries' => [
        'field_labels' => [
            'education_degree' => 'Education degree inquiry :n',
            'criminal-record' => 'Criminal record inquiry',
            'social-insurance' => 'Social insurance inquiry',
        ],
        'validation' => [
            'invalid_education_index' => 'Invalid education record reference for an inquiry.',
        ],
    ],
    'documents' => [
        'max_files_reached' => 'The maximum of :count files for this document type has been reached.',
        'total_max_files_reached' => 'The maximum of :count files for this employee has been reached.',
        'trashed' => 'Document moved to trash.',
        'restored' => 'Document restored.',
    ],
    'validation' => [
        'personnel_code_unique' => 'This personnel code is already assigned to another employee.',
    ],
];
