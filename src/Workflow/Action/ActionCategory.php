<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action;

/**
 * Lets the CMS group and label what a transition does.
 */
enum ActionCategory: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case DOCUMENT = 'document';
    case INTEGRATION = 'integration';
    case STATE = 'state';
    case OTHER = 'other';
}
