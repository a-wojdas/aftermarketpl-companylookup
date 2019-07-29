<?php 

namespace Aftermarketpl\CompanyLookup;

use Throwable;
use Aftermarketpl\CompanyLookup\Exceptions\ViesReaderException;
use Aftermarketpl\CompanyLookup\Models\CompanyAddress;
use Aftermarketpl\CompanyLookup\Models\CompanyData;
use Aftermarketpl\CompanyLookup\Models\CompanyIdentifier;

use SoapClient;

class ViesReader
{
    use Traits\ResolvesVatid;
    use Traits\ValidatesVatid;

    
    public $ws_url = 'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl?wsdl';
    private $api = null;

    /**
     * 
     */
    public function __construct($options = [])
    {
        $this->options = $options;
    
        try {
            $this->api = new SoapClient($this->ws_url);
        } catch(\Throwable $e) {
            throw new ViesReaderException('Checking status currently not available');
        }
    }

    /**
     */
    public function lookup(string $vatid)
    {
        $vatid = $this->validateVatid($vatid);

        list($country, $number) = $this->resolveVatid($vatid);

        try {
            $response = $this->api->checkVat([
                'countryCode' => $country,
                'vatNumber' => $number
            ]);
        } catch(Throwable $e) {
            throw new ViesReaderException('Checking status currently not available [' . $e->getMessage() . ']');
        }

        if(!$response->valid)
        {
            $companyData = new CompanyData;
            $companyData->valid = false;
            $companyData->identifiers[] = new CompanyIdentifier('vat', $vatid);
            return $companyData;
        }
        else
        {
            try {
                list($address, $zip, $city) = Helpers\Address::extract($country, $response->address);
            } catch(Throwable $e) {
                $address = $response->address;
                $zip = '';
                $city = '';
            }

            $address = trim(preg_replace("/^\-\-\-$/", "", $address));

            $companyAddress = new CompanyAddress;
            $companyAddress->country = $country;
            $companyAddress->postalCode = $zip;
            $companyAddress->address = $address;
            $companyAddress->city = $city;

            $companyData = new CompanyData;
            $companyData->valid = true;
            $companyData->name = $response->name;
            
            $companyData->identifiers[] = new CompanyIdentifier('vat', $vatid);
            $companyData->mainAddress = $companyAddress;

            return $companyData;
        }
    }
}