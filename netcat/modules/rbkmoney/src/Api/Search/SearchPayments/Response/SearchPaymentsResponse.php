<?php

namespace src\Api\Search\SearchPayments\Response;

use src\Api\Exceptions\WrongDataException;
use src\Api\Interfaces\ResponseInterface;
use src\Api\RBKmoneyDataObject;
use stdClass;

class SearchPaymentsResponse extends RBKmoneyDataObject implements ResponseInterface
{

    /**
     * @var string | null
     */
    public $continuationToken;

    /**
     * @var array | Payment[] | null
     */
    public $result = [];

    /**
     * @param stdClass $response
     *
     * @throws WrongDataException
     */
    public function __construct(stdClass $response)
    {
        if (property_exists($response, PROPERTY_CONTINUATION_TOKEN)) {
            $this->continuationToken = $response->{PROPERTY_CONTINUATION_TOKEN};
        }

        if (property_exists($response, PROPERTY_RESULT)) {
            foreach ($response->{PROPERTY_RESULT} as $result) {
                $this->result[] = new Payment($result);
            }
        }
    }

}
