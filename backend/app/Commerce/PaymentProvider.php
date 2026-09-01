<?php

namespace App\Commerce;

interface PaymentProvider
{
    /** Return a provider-owned redirect/reference without accepting card data. */
    public function initiate(array $intent): array;

    /** Return a canonical verified event; throw when authenticity or contents fail. */
    public function verify(array $payload): array;
}
