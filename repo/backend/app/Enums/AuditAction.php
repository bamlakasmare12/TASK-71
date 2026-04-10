<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case LogoutAll = 'logout_all';
    case PasswordChange = 'password_change';
    case PasswordRotationRequired = 'password_rotation_required';
    case AccountLocked = 'account_locked';
    case AccountUnlocked = 'account_unlocked';
    case RoleChange = 'role_change';
    case StepUpVerified = 'step_up_verified';
    case StepUpFailed = 'step_up_failed';
    case CaptchaFailed = 'captcha_failed';
    case SessionTimeout = 'session_timeout';
    case LoginAnomaly = 'login_anomaly';
    case DataExport = 'data_export';
    case DataImport = 'data_import';
    case AccountDeleted = 'account_deleted';
    case PolicyEdit = 'policy_edit';
    case ReservationCreated = 'reservation_created';
    case ReservationConfirmed = 'reservation_confirmed';
    case ReservationCancelled = 'reservation_cancelled';
    case ReservationCheckedIn = 'reservation_checked_in';
    case ReservationCheckedOut = 'reservation_checked_out';
    case ReservationRescheduled = 'reservation_rescheduled';
    case BookingFreeze = 'booking_freeze';
    case ReservationExpired = 'reservation_expired';
    case ReservationNoShow = 'reservation_no_show';
    case ReservationPartialAttendance = 'reservation_partial_attendance';
    case DictionaryModified = 'dictionary_modified';
    case FormRuleModified = 'form_rule_modified';
}
