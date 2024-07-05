<?php

namespace NW\WebService\References\Operations\Notification;

abstract class OperationDataRenderer
{
    protected string $dataObject;

    public function render(array $requestData): OperationData
    {
        $dataObject = new ($this->dataObject);
        foreach ($dataObject::REQUIRED_FIELDS as $field) {
            if (!isset($requestData[$field]) && $legallyMissed = !(bool)array_search($field, $dataObject::NULLABLE_FIELDS)) {
                throw new \Exception("Field {$field} is missed!", 400);
            }
            if ($legallyMissed) {
                continue;
            }

            $dataObject->setData($field, $requestData[$field]);
        }
    }
}

class TsReturnOperationDataRenderer extends OperationDataRenderer
{
    protected string $dataObject = TsReturnOperationData::class;
}

abstract class OperationData
{
    public const REQUIRED_FIELDS = [];

    public const NULLABLE_FIELDS = [];

    private array $data = [];

    public function setData(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function getData(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }
}

class TsReturnOperationData extends OperationData
{
    public const
        COMPLAINT_ID_FIELD       = 'complaintId',
        COMPLAINT_NUMBER_FIELD   = 'complaintNumber',
        RESELLER_ID_FIELD        = 'resellerId',
        CREATOR_ID_FIELD         = 'creatorId',
        EXPERT_ID_FIELD          = 'expertId',
        CLIENT_ID_FIELD          = 'clientId',
        CONSUMPTION_ID_FIELD     = 'consumptionId',
        CONSUMPTION_NUMBER_FIELD = 'consumptionNumber',
        AGREEMENT_NUMBER_FIELD   = 'agreementNumber',
        DATE_FIELD               = 'date',
        DIFFERENCES_FIELD        = 'differences',
        NOTIFICATION_TYPE_FIELD  = 'notificationType';

    public const REQUIRED_FIELDS = [
        'complaintId',
        'complaintNumber',
        'resellerId',
        'creatorId',
        'expertId',
        'clientId',
        'consumptionId',
        'consumptionNumber',
        'agreementNumber',
        'date',
        'differences',
        'resellerId',
        'notificationType'
    ];

    public const NULLABLE_FIELDS = [
        'differences',
        'resellerId'
    ];

    private Seller     $reseller;
    private Contractor $client;
    private Employee   $creator;
    private Employee   $expert;

    public function getReseller(): Seller
    {
        if ($this->reseller) {
            return $this->reseller;
        }

        try {
            $this->reseller = Seller::getById((int)$this->getData(self::RESELLER_ID_FIELD));
        } catch (\Exception $e) {
            throw new \Exception('Seller not found!', 400);
        }
        return $this->reseller;
    }

    public function getClient(): Contractor
    {
        if ($this->client) {
            return $this->client;
        }

        try {
            $this->client = Contractor::getById($this->getData(self::CLIENT_ID_FIELD));
        } catch (\Exception $e) {
            throw new \Exception('Client not found!', 400);
        }

        if ($this->client->type !== Contractor::TYPE_CUSTOMER
            || $this->client->Seller->id !== $this->getData(self::RESELLER_ID_FIELD))

            return $this->client;
    }

    public function getCreator(): Employee
    {
        if ($this->creator) {
            return $this->creator;
        }

        try {
            $this->creator = Employee::getById($this->getData(self::CREATOR_ID_FIELD));
        } catch (\Exception $e) {
            throw new \Exception('Creator not found!', 400);
        }

        return $this->creator;
    }

    public function getExpert(): Employee
    {
        if ($this->expert) {
            return $this->expert;
        }

        try {
            $this->expert = Employee::getById($this->getData(self::EXPERT_ID_FIELD));
        } catch (\Exception $e) {
            throw new \Exception('Expert not found!', 400);
        }

        return $this->expert;
    }
}
