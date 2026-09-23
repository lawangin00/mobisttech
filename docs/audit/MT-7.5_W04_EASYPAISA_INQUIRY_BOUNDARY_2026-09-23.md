# W04 Easypaisa REST inquiry response boundary — 23 Sep 2026

- Preparatory REST v4 transport now checks inquiry `accountNum`, known `transactionStatus`, documented `paymentMode`, and exact non-negative decimal amount shape in addition to request-bound order/store identity before returning an inquiry response.
- This is not payment settlement: the REST transport remains unregistered, external providers default OFF, and no inquiry or MA initiation response is allowed to mark orders PAID. A payment-specific amount and payment-mode comparison plus verified callback/inquiry acceptance remain pending.
- Synthetic `W04EasypaisaRestClientTest`: 4/4 PASS (16 assertions), scoped Pint PASS. No MySQL, merchant account or gateway contacted.
- W04 IN PROGRESS; MT-7.5 15/27 DONE / 12 OPEN. H-02 merchant variant, signed IPN/callback contract, credential provisioning and real sandbox verification remain HOLD.
