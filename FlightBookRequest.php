<?php


namespace Inteletravel\Msfflight\Http\Requests;


use Exception;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Inteletravel\Msfflight\Classes\Session;
use Inteletravel\Msfflight\Service\MyFareBoxService;

/**
 * Class FlightBookRequest
 * @package Inteletravel\Msfflight\Http\Requests
 */
class FlightBookRequest extends FormRequest
{
    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'routeId' => 'required|min:20',
            'sessionId' => 'required|session_expired',
            'email' => 'required|email',
            'passengers.*.firstName' => 'required|regex:/[A-Z][a-zA-Z]{2,28}$/',
            'passengers.*.middleName' => 'regex:/^[a-zA-Z]{2,10}$/',
            'passengers.*.lastName' => 'required|regex:/[A-Z][a-zA-Z]{2,28}$/',
            'passengers.*.dob' => 'required|date_format:Y-m-d',
            'passengers.*.gender' => 'sometimes|in:M,F,U',
            'passengers.*.passportNo' => 'sometimes',
            'passengers.*.passportExpiry' => 'sometimes|date_format:Y-m-d',
            'travelDetails.travelBeginDate' => 'required|date_format:Y-m-d\TH:i:s',
            'travelDetails.flightNumber' => 'sometimes',
            'travelDetails.source' => 'required',
            'travelDetails.destination' => 'required',
            'extraServices.*' => 'sometimes|extra_services',
            'extraServices.*.serviceId' => 'sometimes|numeric',
            'extraServices.*.quantity' => 'sometimes|numeric',
            'country_code' => 'required|regex:/^[0-9]{1,5}$/',
            'phone_number' => 'required|regex:/^[0-9]{5,15}$/',
            'country' => 'regex:/^[A-Z]{2,5}$/',
            'zip' => 'numeric',
            'area_code' => 'required|numeric',
            'fareType' => 'required|in:GDS,LCC',
            'passengers.*' => 'passenger_year|passenger_type|name_prefix',
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'country_code.regex' => 'You have entered the invalid country code',
            'phone_number.regex' => 'You have entered the invalid phone_number',
            'passengers.*.firstName.regex' => 'Passenger firstName must be a string and not exceed more than 28 characters and less 2',
            'passengers.*.middleName.regex' => 'Passenger middleName must be a string and not exceed more than 10 characters and less 2',
            'passengers.*.lastName.regex' => 'Passenger lastName must be a string and not exceed more than 28 characters and less 2',
            'passengers.*.dob.required' => 'Passenger date of birth is required',
            'passengers.*.dob.date_format' => 'Passenger date of birth must be in the format yyyy-mm-dd',
            'passengers.*.passportExpiry.date_format' => 'Passenger passport expiry date must be in the format yyyy-mm-dd',
            'travelDetails.travelBeginDate.date_format' => 'YYYY-MM-DDT00:00:00 format for the flight departure date and time (As per origin time zone)',
            'fareType.in' => 'Fare type must be GDS or LCC.'
        ];
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData(): array
    {
        return $this->all();
    }

    /**
     * @param $validator
     * @throws Exception
     */
    public function withValidator($validator)
    {
        $validator->addExtension('passenger_year', function ($attribute, $passenger, $parameters, $validator) {
            if(strtolower($passenger['passengerType'] ) === self::PASSENGER_TYPE_CHILD){
                if(ageCalculator($passenger['dob']) > MyFareBoxService::PASSENGER_CHILD_YEAR ){
                    $validator->errors()->add($attribute.'.dob', 'For child, the age should be less than '. MyFareBoxService::PASSENGER_CHILD_YEAR .' years');
                }
            }
            if(strtolower($passenger['passengerType'] ) === self::PASSENGER_TYPE_INFANT){
               if(ageCalculator($passenger['dob'] ) > MyFareBoxService::PASSENGER_INFANT_YEAR){
                   $validator->errors()->add($attribute.'.dob', 'For infant , the age should be less than '. MyFareBoxService::PASSENGER_INFANT_YEAR .' years');
               }
            }
            return true;
        });

        $validator->addExtension('passenger_type', function ($attribute, $passenger, $parameters, $validator) {
            if( !in_array(strtolower($passenger['passengerType'] ),self::PASSENGER_TYPES)){
                $validator->errors()->add($attribute.'.passengerType', 'please select a valid passenger type');
            }
            return true;
        });

        $validator->addExtension('name_prefix', function ($attribute, $passenger, $parameters, $validator) {
            if( !in_array(strtolower($passenger['namePrefix'] ),self::PASSENGER_TITLES)){
                $validator->errors()->add($attribute.'.namePrefix', 'Please select a valid passenger title');
            }
            return true;
        });

        $validator->addExtension('session_expired', function ($attribute, $value, $parameters, $validator) {
            $validation = Session::findFlight($value,request()->routeId);
            if( $validation instanceof Exception){
                $validator->errors()->add($attribute, $validation->getMessage());
            }
            return true;
        });

        $validator->addExtension('extra_services', function ($attribute, $service, $parameters, $validator) {
            if( !is_array($service) || !array_key_exists('serviceId', $service) || !array_key_exists('quantity', $service)){
                $validator->errors()->add($attribute, 'Should be an array with `serviceId` keys and `quantity`');
            }
            return true;
        });
    }

    /**
     * @param Validator $validator
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The given data is invalid',
            'data' => [],
            'errors' => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST));
    }
}
