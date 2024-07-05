<?php

namespace NW\WebService\References\Operations\Notification;


class Contractor
{
    const TYPE_CUSTOMER = 0;

    public function __construct(
        public int    $id,
        public int    $type,
        public string $name
    ) {}

    public static function getById(int $userId): self
    {
        return new self($userId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . (string)$this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class ContractorRepository
{

}

class Status
{
    public const STATUSES = [
        'Completed',
        'Pending',
        'Rejected'
    ];

    public static function getName(int $id): bool|string
    {
        return array_search($id, self::STATUSES);
    }
}

abstract class ReferencesOperation
{
    private OperationDataRenderer $dataRenderer;

    protected function getData(): OperationData
    {
        return $this->dataRenderer->render($this->getRequest('data'));
    }

    abstract public function doOperation(): array;

    private function getRequest($pName): mixed
    {
        return $_REQUEST[$pName] ?? null;
    }
}

class Config
{
    public static function getResellerEmailFrom()
    {
        // test method imitation
        return 'contractor@example.com';
    }

    public static function getEmailsByPermit($resellerId, $event)
    {
        // test method imitation
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }
}

class NotificationEvents
{
    public const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    public const NEW_RETURN_STATUS    = 'newReturnStatus';
}
