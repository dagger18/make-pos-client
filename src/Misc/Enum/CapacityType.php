<?php

namespace App\Misc\Enum;

enum CapacityType: string
{
    case NetworkBandwidth   = 'network_bandwidth';
    case EmailSend          = 'email_send';
    case ResetPassword      = 'reset_password';
    case FileStorage        = 'file_storage';
    case DocumentOperations = 'document_operations';
    case MaxUsers           = 'max_users';
    case MaxOrders          = 'max_orders';
    case MaxProducts        = 'max_products';
}
