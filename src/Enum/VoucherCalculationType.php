<?php

namespace Greendot\EshopBundle\Enum;

enum VoucherCalculationType: string
{
    case WithoutVoucher = 'Price without voucher deduction';
    case WithVoucher = 'Price with voucher deduction to zero or higher value';
    case WithVoucherToMinus = 'Price with voucher deduction possibly to negative value';
}