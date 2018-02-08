<?php

namespace PamCore\Db;

class Exception extends \Exception
{
    const CODE_DUPLICATE_ENTRY = 1062;
    const CODE_INVALID_VALUE = 1063;
}