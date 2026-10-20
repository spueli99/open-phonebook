<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

enum AuditEventType: string
{
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserRoleChanged = 'user.role_changed';
    case InvitationCreated = 'invitation.created';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationRenewed = 'invitation.renewed';
    case InvitationRevoked = 'invitation.revoked';
}
