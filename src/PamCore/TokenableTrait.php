<?php

namespace PamCore;

trait TokenableTrait
{
    public function generateToken()
    {
        return base64_encode(openssl_random_pseudo_bytes(TokenableInterface::BIN_LENGTH));
    }
}