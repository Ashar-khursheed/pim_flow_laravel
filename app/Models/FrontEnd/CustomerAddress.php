<?php

namespace App\Models\FrontEnd;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CustomerAddress extends Model
{
    protected $table = 'ec_customer_addresses';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'zip_code',
        'customer_id',
        'is_default',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function getCountryNameAttribute()
    {
        $value = $this->country_id;

        if (is_numeric($value)) {
            $countryName = $this->locationCountry->name;

            if ($countryName) {
                return $countryName;
            }
        }

        return $value;
    }

    public function getStateNameAttribute()
    {
        $value = $this->state_id;
        if (is_numeric($value)) {
            $stateName = $this->locationState->name;

            if ($stateName) {
                return $stateName;
            }
        }

        return $value;
    }

    public function getCityNameAttribute()
    {
        $value = $this->city_id;

        if (is_numeric($value)) {
            $cityName = $this->locationCity->name;

            if ($cityName) {
                return $cityName;
            }
        }
        return $value;
    }

    public function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => implode(', ', array_filter([
                $this->address,
                $this->city_name,
                $this->state_name,
                $this->country_name ? $this->country_name : '',
                $this->zip_code ? $this->zip_code : '',
            ])),
        );
    }
}