# W04 Easypaisa REST v4 preparatory transport — 23 Sep 2026

Sources: user-supplied `API Integration Guide without RSA.pdf` pp. 2, 5–10 and `Easypaisa Mobile Account MA API Integration Guide with RSA.pdf` pp. 4–8.

This checkpoint implements a staging-only, unregistered REST v4 MA initiation/inquiry transport. It has **no** credentials in source and does not register itself in `PaymentProviders`; the existing checkout expects an HTTPS hosted redirect while the documented MA flow does not provide one. No checkout, real payment, IPN verification, settlement, refund or merchant account activation was claimed. External gateways remain OFF.

Synthetic HTTP fixture: `W04EasypaisaRestClientTest` 3/3 PASS (12 assertions). Outstanding: merchant-specific API variant and issued credentials, exact IPN authentication/settlement contract, dedicated customer MA/OTC flow and verified sandbox acceptance. H-02 HOLD, W04 IN PROGRESS, MT-7.5 15/27 DONE / 12 OPEN.
