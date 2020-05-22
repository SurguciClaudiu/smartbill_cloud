<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */
define('SMARTBILL_PLUGIN_VERSION', '1.2.5');
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'SmartBill_Integration',
    __DIR__
);
