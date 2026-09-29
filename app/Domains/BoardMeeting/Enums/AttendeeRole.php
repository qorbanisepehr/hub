<?php

namespace App\Domains\BoardMeeting\Enums;

enum AttendeeRole: string
{
    case Chair = 'chair';
    case Member = 'member';
    case Secretary = 'secretary';
    case Observer = 'observer';
    case Guest = 'guest';
}
