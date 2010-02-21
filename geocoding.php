<?php
/**
 * Google Geocoding class
 *
 * More information: http://code.google.com/intl/en/apis/maps/documentation/geocoding/index.html
 *
 * Note:  the geocoding service may only be used in conjunction with displaying results on a Google map; geocoding results
 * without displaying them on a map is prohibited. For complete details on allowed usage, consult the Maps API Terms of
 * Service License Restrictions: http://code.google.com/intl/en/apis/maps/terms.html#section_10_12
 *
 * Geocoding is a time and resource intensive task. Whenever possible, pre-geocode known addresses, and store your results
 * in a temporary cache or database of your own design.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to,
 * the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the
 * author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but
 * not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption)
 * however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or
 * otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author		Bert Pattyn <project-google-geocoding@dextrose.be>
 * @version		1.0
 *
 * @license		BSD License
 */
class GoogleGeocoding
{
  /**
   * url for the Google Maps API Geocoding Service
   */
  const GMAPS_API_URL = 'http://maps.google.com/maps/geo?';

  /**
   * port for the Google Maps API Geocoding Service
   */
  const GMAPS_API_PORT = 80;

  /**
   * the version of this class
   */
  const VERSION = 1.1;

  /**
   * the address to geocode
   *
   * @var string
   */
  private $address = '';


  /**
   * the country code (ccTLD)
   *
   * @link http://en.wikipedia.org/wiki/CcTLD wikipedia: ccTLD
   * @var string
   */
  private $country = '';


  /**
   * Google Maps API key
   *
   * @link http://code.google.com/intl/en/apis/maps/signup.html Sign Up for the Google Maps API
   * @var	string
   */
  private $key = '';


  /**
   * latitude and longitude parameters for reverse geocoding
   *
   * @var array
   */
  private $reverseLngLat = array('lng' => 0.0, 'lat' => 0.0);


  /**
   * Indicates whether or not the geocoding request comes from a device with a location sensor
   *
   * @var boolean
   */
  private $sensor = false;


  /**
   * The timeout for the REST request
   *
   * @var	int
   */
  private $timeOut = 60;


  /**
   * The user agent
   *
   * @var	string
   */
  private $userAgent;


  /**
   * The viewport
   *
   * @var array
   */
  private $viewport = array('center_lat' => 0.0, 'center_lng' => 0.0, 'span_lat' => 0.0, 'span_lng' => 0.0);


  /**
   * constructor
   *
   * @return  void
   * @param   string  $key Google Maps key
   */
  public function __construct($key = null)
  {
    if($key !== null) $this->setKey($key);
  }


  /**
   * build the result based on a google placemark
   *
   * @param   SimpleXMLElement  $placemark
   * @return  array
   */
  private function buildResultFromPlacemark($placemark)
  {
    // init array
    $result = array();
    $result['coordinates']['lng'] = null;
    $result['coordinates']['lat'] = null;

    // accuracy
    $result['accuracy']                             = isset($placemark->AddressDetails['Accuracy']) ? (int) $placemark->AddressDetails['Accuracy'] : null;

    // full address
    $result['address']                              = isset($placemark->address) ? (string) $placemark->address : null;

    // details
    // @todo full support for xAL, eXtensible Address Language
    $result['details']['countryName']               = isset($placemark->AddressDetails->Country->CountryName) ? (string) $placemark->AddressDetails->Country->CountryName : null;
    $result['details']['countryCode']               = isset($placemark->AddressDetails->Country->CountryNameCode) ? (string) $placemark->AddressDetails->Country->CountryNameCode : null;
    $result['details']['AdministrativeAreaName']    = isset($placemark->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName : null;
    $result['details']['SubAdministrativeAreaName'] = isset($placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->SubAdministrativeAreaName) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->SubAdministrativeAreaName : null;
    $result['details']['LocalityName']              = isset($placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->LocalityName) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->LocalityName : null;
    $result['details']['DependentLocalityName']     = isset($placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->DependentLocalityName) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->DependentLocalityName : null;
    $result['details']['PostalCode']                = isset($placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->PostalCode->PostalCodeNumber) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->PostalCode->PostalCodeNumber : null;
    $result['details']['Street']                    = isset($placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->Thoroughfare->ThoroughfareName) ? (string) $placemark->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality->DependentLocality->Thoroughfare->ThoroughfareName : null;

    // coordinates
    $coordinates = isset($placemark->Point->coordinates) ? explode(',', (string) $placemark->Point->coordinates) : null;

    if(count($coordinates) > 2)
    {
      $result['coordinates']['lng'] = (float) $coordinates[0];
      $result['coordinates']['lat'] = (float) $coordinates[1];
    }

    // return
    return $result;
  }


  /**
   * Make the call
   *
   * @param	bool[optional] $reverse reverse geocoding?
   * @return	string
   */
  private function doCall($reverse = false)
  {
    // get parameters
    $param = array();

    // add key if available
    if($this->getKey() !== '') $param['key'] = $this->getKey();

    $param['sensor'] = $this->getSensor()?'true':'false';
    $param['output'] = 'xml';

    // add country code biasing
    if($this->getCountry() !== '') $param['gl'] = $this->getCountry();

    // add viewport biasing
    if($this->viewport['center_lat'] !== 0.0 ||
      $this->viewport['center_lng'] !== 0.0 ||
      $this->viewport['span_lat']   !== 0.0 ||
      $this->viewport['span_lng']   !== 0.0)
    {
      $param['ll'] = (string) $this->viewport['center_lat'] . ',' . (string) $this->viewport['center_lng'];
      $param['spn'] = (string) $this->viewport['span_lat'] . ',' . (string) $this->viewport['span_lng'];
    }

    // address or coordinates?
    if($reverse) $param['q'] = $this->getReverseLngLat();
    else $param['q'] = $this->getAddress();

    // build url parameters
    $paramStr = array();
    foreach($param as $key => $value) $paramStr[] = $key . '=' . urlencode($value);

    // set options
    $options = array();
    $options[CURLOPT_URL] = self::GMAPS_API_URL . implode('&', $paramStr);
    $options[CURLOPT_PORT] = self::GMAPS_API_PORT;
    $options[CURLOPT_USERAGENT] = $this->getUserAgent();
    $options[CURLOPT_FOLLOWLOCATION] = true;
    $options[CURLOPT_RETURNTRANSFER] = true;
    $options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();

    // init
    $curl = curl_init();

    // set options
    curl_setopt_array($curl, $options);

    // execute
    $response = curl_exec($curl);
    $headers = curl_getinfo($curl);

    // fetch errors
    $errorNumber = curl_errno($curl);
    $errorMessage = curl_error($curl);

    // close
    curl_close($curl);

    // validate body
    $result = @simplexml_load_string($response);

    // invalid xml?
    if(!($result instanceof SimpleXMLElement)) throw new GoogleGeocodingException('invalid XML returned');

    // invalid headers
    if(!in_array($headers['http_code'], array(0, 200)))
    {
      throw new GoogleGeocodingException(null, (int) $headers['http_code']);
    }

    // @todo handle google response status codes

    return $result->Response;
  }


  /**
   * Get the address
   *
   * @return	string
   */
  private function getAddress()
  {
    return (string) $this->address;
  }


  /**
   * Get the ccTLD country code
   *
   * @return string
   */
  private function getCountry()
  {
    return (string) $this->country;
  }


  /**
   * Get the Google Maps Key
   *
   * @return	string
   */
  private function getKey()
  {
    return (string) $this->key;
  }


  /**
   * Get the latitude and longitude string
   *
   * @return string
   */
  private function getReverseLngLat()
  {
    return (string) $this->reverseLngLat['lat'] . ',' . (string) $this->reverseLngLat['lng'];
  }


  /**
   * Get the sensor
   *
   * @return	int
   */
  private function getSensor()
  {
    return (bool) $this->sensor;
  }


  /**
   * Get the timeout
   *
   * @return	int
   */
  private function getTimeOut()
  {
    return (int) $this->timeOut;
  }


  /**
   * Get the useragent
   *
   * @return	string
   */
  private function getUserAgent()
  {
    return (string) 'PHP Google Geocoding/'. self::VERSION . (empty($this->userAgent) ? '' : ' '. $this->userAgent);
  }


  /**
   * Set the address
   *
   * @return  void
   * @param   mixed   $address address as array or string
   */
  private function setAddress($address)
  {
    if(is_array($address)) $this->address = trim(implode(',', $address));
    else $this->address = (string) trim($address);
  }


  /**
   * set the countrycode (ccTLD) to narrow down the possible results.
   * This is a ccTLD, not a ISO 3166-1 code (mostly identical).
   *
   * @link http://en.wikipedia.org/wiki/CcTLD
   * @param string $country
   */
  public function setCountry($country)
  {
    $this->country = (string) $country;
  }


  /**
   * Set Google Maps key
   *
   * @link http://code.google.com/intl/en/apis/maps/signup.html Sign Up for the Google Maps API
   * @return	void
   * @param	string $key
   */
  public function setKey($key)
  {
    $this->key = (string) $key;
  }


  /**
   * set a latitude and longitude
   *
   * @param float $lng
   * @param float $lat
   */
  private function setReverseLngLat($lng, $lat)
  {
    $this->reverseLngLat['lng'] = (float) $lng;
    $this->reverseLngLat['lat'] = (float) $lat;
  }


  /**
   * Set the sensor
   *
   * @return	void
   * @param	bool $sensor
   */
  public function setSensor($sensor)
  {
    $this->sensor = (bool) $sensor;
  }


  /**
   * Set the timeout
   *
   * @return	void
   * @param	int $seconds
   */
  public function setTimeOut($seconds)
  {
    $this->timeOut = (int) $seconds;
  }


  /**
   * Set the user-agent for you application
   * It will be appended to ours
   *
   * @param	string $userAgent
   * @return	void
   */
  public function setUserAgent($userAgent)
  {
    $this->userAgent = (string) $userAgent;
  }


  /**
   * Set the viewport.
   * $centerLat and $cenerLng defines the latitude/longitude coordinates of the center of this bounding box.
   * $spanLat and $spanLng defines the latitude/longitude "span" of this bounding box in both vertical
   * and horizontal dimensions.
   *
   * @param $centerLng the longitude of the center
   * @param $centerLat the latitude of the center
   * @param $spanLng the longitude of the span (in contrast to the longitude of the center)
   * @param $spanLat the latitude of te span (in contrast to the latitude of the center)
   * @return void
   */
  public function setViewport($centerLng, $centerLat, $spanLng, $spanLat)
  {
    $this->viewport['center_lng'] = (float) $centerLng;
    $this->viewport['center_lat'] = (float) $centerLat;
    $this->viewport['span_lng'] = (float) $spanLng;
    $this->viewport['span_lat'] = (float) $spanLat;
  }


  /**
   * Get a string representation of the accuracy
   *
   * @param int $accuracy
   * @return string
   */
  public function getAccuracyDescription($accuracy)
  {
    switch((int) $accuracy)
    {
      case 1:
        return 'Country level accuracy';
      case 2:
        return 'Region (state, province, prefecture, etc.) level accuracy';
      case 3:
        return 'Sub-region (county, municipality, etc.) level accuracy';
      case 4:
        return 'Town (city, village) level accuracy';
      case 5:
        return 'Post code (zip code) level accuracy';
      case 6:
        return 'Street level accuracy';
      case 7:
        return 'Intersection level accuracy';
      case 8:
        return 'Address level accuracy';
      case 9:
        return 'Premise (building name, property name, shopping center, etc.) level accuracy';
      default:
        return 'Unknown accuracy';

    }
  }


  /**
   * Get the lattitude, longitude and accuracy of an address
   *
   * @param   mixed $address address as array or string
   * @return  array an array with 3 elements: lng, lat and accuracy
   */
  public function getLngLat($address)
  {
    $this->setAddress($address);

    // throw error if there is no error
    if($this->getAddress() === '') throw new GoogleGeocodingException('no address provided');

    // do the call
    $response = $this->doCall();
    
    // init array
    $result = array();

    // coordinates
    $coordinates = isset($response->Placemark[0]->Point->coordinates) ? explode(',', (string) $response->Placemark[0]->Point->coordinates) : null;

    if(count($coordinates) > 2)
    {
      $result['lng'] = (float) $coordinates[0];
      $result['lat'] = (float) $coordinates[1];
    }
    else
    {
      $result['lng'] = null;
      $result['lat'] = null;
    }

    // accuracy
    $result['accuracy'] = isset($response->Placemark[0]->AddressDetails['Accuracy']) ? (int) $response->Placemark[0]->AddressDetails['Accuracy'] : null;

    return $result;
  }


  /**
   * Get all geo information
   *
   * @param mixed $address address as array or string
   * @return array
   */
  public function getInfo($address)
  {
    $this->setAddress($address);

    // throw error if there is no address
    if($this->getAddress() === '') throw new GoogleGeocodingException('no address provided');

    // do the call
    $response = $this->doCall();

    // return the results
    return $this->buildResultFromPlacemark($response->Placemark[0]);
  }



  /**
   * Get all the placemarks of a latitude and longitude
   *
   * @param float $lng longitude
   * @param float $lat latitude
   * @return array
   */
  public function getReverseAll($lng, $lat)
  {
    // set longitude and latitude
    $this->setReverseLngLat($lng, $lat);

    // do the call
    $response = $this->doCall(true);

    // get the results
    $result = array();
    foreach($response->Placemark as $placemark) $result[] = $this->buildResultFromPlacemark($placemark);

    // return the result
    return $result;
  }



  /**
   * Get the most specific placemark of a latitude and longitude
   *
   * @param float $lng longitude
   * @param float $lat latitude
   * @return array
   */
  public function getReverse($lng, $lat)
  {
    // set longitude and latitude
    $this->setReverseLngLat($lng, $lat);

    // do the call
    $response = $this->doCall(true);

    // return the result
    return $this->buildResultFromPlacemark($response->Placemark[0]);
  }
}


/**
 * Google Geocoding Exception class
 *
 * @author Bert Pattyn <project-google-geocoding@dextrose.be>
 */
class GoogleGeocodingException extends Exception
{
  /**
   * Http header-codes
   *
   * @var	array
   */
  private $aStatusCodes = array(
    200 => 'No errors occurred; the address was successfully parsed and its geocode was returned.',
    500 => 'A geocoding or directions request could not be successfully processed, yet the exact reason for the failure is unknown.',
    601 => 'An empty address was specified in the HTTP q parameter.',
    602 => 'No corresponding geographic location could be found for the specified address, possibly because the address is relatively new, or because it may be incorrect.',
    603 => 'The geocode for the given address or the route for the given directions query cannot be returned due to legal or contractual reasons.',
    610 => 'The given key is either invalid or does not match the domain for which it was given.',
    620 => 'The given key has gone over the requests limit in the 24 hour period or has submitted too many requests in too short a period of time. If you\'re sending multiple requests in parallel or in a tight loop, use a timer or pause in your code to make sure you don\'t send the requests too quickly.'
  );


  /**
   * Default constructor
   *
   * @param	string[optional] $message
   * @param	int[optional] $code
   * @return	void
   */
  public function __construct($message = null, $code = null)
  {
    // set message
    if($message === null && isset($this->aStatusCodes[(int) $code])) $message = $this->aStatusCodes[(int) $code];

    // call parent
    parent::__construct((string) $message, $code);
  }
}