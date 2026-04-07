<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow;


enum PurchaseWorkflowContract: string
{
    case NAME = 'purchase_flow';

    // STATES
    case S_WISHLIST = 'wishlist';
    case S_CART = 'cart';

    // TRANSITIONS
    case T_INIT_WISHLIST = 'init_wishlist';
    case T_INIT_CART = 'init_cart';
    case T_CHECKOUT = 'checkout';
    case T_PAY_PAY = 'payment';
    case T_PAY_FAIL = 'payment_issue';
    case T_LOG_PREPARE_FOR_PICKUP = 'log_prepare_for_pickup';
    case T_LOG_SEND = 'send';
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
