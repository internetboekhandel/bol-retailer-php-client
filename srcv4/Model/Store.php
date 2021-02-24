<?php

namespace Picqer\BolRetailerV4\Model;

// This class is auto generated by OpenApi\ModelGenerator
class Store extends AbstractModel
{
    /**
     * Returns the definition of the model: an associative array with field names as key and
     * field definition as value. The field definition contains of
     * model: Model class or null if it is a scalar type
     * array: Boolean whether it is an array
     * @return array The model definition
     */
    public function getModelDefinition(): array
    {
        return [
            'productTitle' => [ 'model' => null, 'array' => false ],
            'visible' => [ 'model' => CountryCode::class, 'array' => true ],
        ];
    }

    /**
     * @var string The product title for the product associated with this offer.
     */
    public $productTitle;

    /**
     * @var CountryCode[]
     */
    public $visible = [];

    /**
     * Returns an array with the countryCodes from visible.
     * @return string[] CountryCodes from visible.
     */
    public function getVisibleCountryCodes(): array
    {
        return array_map(function ($model) {
            return $model->countryCode;
        }, $this->visible);
    }

    /**
     * Sets visible by an array of countryCodes.
     * @param string[] $countryCodes CountryCodes for visible.
     */
    public function setVisibleCountryCodes(array $countryCodes): void
    {
        $this->visible = array_map(function ($countryCode) {
            return CountryCode::constructFromArray(['countryCode' => $countryCode]);
        }, $countryCodes);
    }

    /**
     * Adds a new CountryCode to visible by countryCode.
     * @param string $countryCode CountryCode for the CountryCode to add.
     */
    public function addVisibleCountryCode(string $countryCode): void
    {
        $this->visible[] = CountryCode::constructFromArray(['countryCode' => $countryCode]);
    }
}
