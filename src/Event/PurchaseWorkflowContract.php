<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Event;


enum PurchaseWorkflowContract: string
{
    case NAME = 'purchase_flow';
    case T_CHECKOUT = 'receive';
    case T_PAY_PAY = 'payment';
    case T_PAY_FAIL = 'payment_issue';
    case T_LOG_PREPARE_FOR_PICKUP = 'prepare_for_pickup';
    case T_LOG_SEND = 'send';
    case T_LOG_PICK_UP = 'pick_up';
    case T_CANCEL = 'cancellation';
    case T_COMPLETE = 'complete';

    public static function eventName(string $event, self $transition = null): string
    {
        if ($transition === null) {
            return sprintf('workflow.%s.%s',
                self::NAME->value,
                $event,
            );
        }

        return sprintf('workflow.%s.%s.%s',
            self::NAME->value,
            $event,
            $transition->value,
        );
    }
}
