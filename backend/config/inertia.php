<?php

return [
    // The POS uses client rendering. No extra Node SSR service or request recorder is required.
    'ssr' => ['enabled' => false],
    'devtools' => ['enabled' => false],
    'history' => ['encrypt' => true],
];
