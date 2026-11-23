<?php

return [
    // Hanya diaktifkan selama gangguan katalog yang diumumkan operator. Diagnosis
    // unresolved tetap perlu alasan dokter dan tidak dianggap siap interoperabilitas.
    'downtime_active' => env('CLINIC_CODING_DOWNTIME', false),
];
