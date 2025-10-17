<?php
 

namespace App\Services;

use App\Models\Version;

class VersionService
{
         
    /**
     * Calculate sales tax for an order.
     */
    public function createVersion(array $data)
    {
        Version::create($data);
    }
}
