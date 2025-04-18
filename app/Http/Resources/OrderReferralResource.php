<?php
// app/Http/Resources/OrderReferralResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'ip' => $this->ip,
            'landing_domain' => $this->landing_domain,
            'landing_page' => $this->landing_page,
            'landing_params' => $this->landing_params,
            'referral' => $this->referral,
            'gclid' => $this->gclid,
            'fclid' => $this->fclid,
            'utm_source' => $this->utm_source,
            'utm_campaign' => $this->utm_campaign,
            'utm_medium' => $this->utm_medium,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
            'referrer_url' => $this->referrer_url,
            'referrer_domain' => $this->referrer_domain,
            'order_id' => $this->order_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}