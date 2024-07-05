<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    public function __construct() {
        $this->dataRenderer = new TsReturnOperationDataRenderer::class;
    }


    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        /**
         * Очень много кода с обработкой массива data в методе, которому совершенно
         * не нужен этот код. Полагаю эту функциональность можно вынести в абстрактный класс,
         * если архитектурой подразумевается, что любые операции используют данные из реквеста.
         */
        $data = $this->getData();

        $result = $this->initEmptyResultArray();

        // Для чего выносить функциональность из класса?
        // Тем более поля name и id в классе - обязательные, значит $client->getFullName() не будет empty
        // $cFullName - бесполезная переменная
        /**
        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {
        $cFullName = $client->name;
        }
         */

        $templateData = [
            'COMPLAINT_ID'       => (int)$data->getData($data::COMPLAINT_ID_FIELD),
            'COMPLAINT_NUMBER'   => (string)$data->getData($data::COMPLAINT_NUMBER_FIELD),
            'CREATOR_ID'         => (int)$data->getData($data::CREATOR_ID_FIELD),
            'CREATOR_NAME'       => $data->getCreator()->getFullName(),
            'EXPERT_ID'          => $data->getData($data::EXPERT_ID_FIELD),
            'EXPERT_NAME'        => $data->getExpert()->getFullName(),
            'CLIENT_ID'          => (int)$data->getData($data::CLIENT_ID_FIELD),
            'CLIENT_NAME'        => $data->getClient()->getFullName(),
            'CONSUMPTION_ID'     => (int)$data->getData($data::CONSUMPTION_ID_FIELD),
            'CONSUMPTION_NUMBER' => (string)$data->getData($data::CONSUMPTION_NUMBER_FIELD),
            'AGREEMENT_NUMBER'   => (string)$data->getData($data::AGREEMENT_NUMBER_FIELD),
            'DATE'               => (string)$data->getData($data::DATE_FIELD),
            'DIFFERENCES'        => $this->getDifferences(
                (int)$data->getData($data::NOTIFICATION_TYPE_FIELD),
                (int)$data->getData($data::RESELLER_ID_FIELD),
                $data->getData($data::DIFFERENCES_FIELD)
            ),
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $result['notificationEmployeeByEmail'] = $this->sendEmployeeEmailNotification(
            $templateData,
            (int)$data->getData($data::RESELLER_ID_FIELD)
        );

        $differences = $data->getData($data::DIFFERENCES_FIELD) ?? [];

        if (
            !(int)$data->getData($data::NOTIFICATION_TYPE_FIELD)
            || empty($differences['to'])
        ) {
            return $result;
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        $result['notificationClientByEmail'] = $this->sendClientEmailNotifications(
            $templateData,
            (int)$data->getData($data::RESELLER_ID_FIELD),
            $differences,
            $data->getClient()
        );

        list($resultStatus, $errors) = $this->sendClientSmsNotification(
            $templateData,
            (int)$data->getData($data::RESELLER_ID_FIELD),
            $differences,
            $data->getClient()
        );

        $result['notificationClientBySms']['isSent'] = $resultStatus;
        $result['notificationClientBySms']['message'] = $errors;

        /**
         * Усложненная структура для выходного массива, особенно если мы говорим об архитектуре REST
         * Лучше возвращать просто код 200, если все необходимые уведомления были отправлены и при необходимости
         * составлять дополнительный массив с названиями неотправленных уведомлений.
         */
        return $result;
    }

    private function getDifferences(int $notificationType, int $resellerId, ?array $differences): string
    {
        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            return __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($differences)) {
            return __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$differences['from']),
                'TO' => Status::getName((int)$differences['to']),
            ], $resellerId);
        }
    }

    private function sendEmployeeEmailNotification(array $templateData, int $resellerId): bool
    {
        $emailFrom = Config::getResellerEmailFrom($resellerId);
        $emails = Config::getEmailsByPermit($resellerId, 'tsGoodsReturn');

        if ((empty($emailFrom) || empty($client->email))) {
            return false;
        }

        foreach ($emails as $email) {
            try {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        /** Не стал бы использовать здесь шаблонизацию текста
                         * Или стал, если фреймворк позволяет использовать разные lang файлы.
                         * Здесь я не вижу конкретизации - откуда именно брать шаблоны, а значит, подозреваю,
                         * Что они берутся из стандартного общего файла, а это выглядит не очень красиво
                         */
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                // log sent messages
            } catch (\Exception $e) {
                // log exception
                return false;
            }
        }

        return true;
    }

    private function sendClientEmailNotifications(
        array      $templateData,
        int        $resellerId,
        array      $differences,
        Contractor $client
    ): bool {
        if (empty($emailFrom) || empty($client->email)) {
            return false;
        }
        try {
            MessagesClient::sendMessage([
                0 => [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo' => $client->email,
                    'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                    'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                ],
            ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$differences['to']);
        } catch (\Exception $e) {
            // log exception
            return false;
        }

        return true;
    }

    private function sendClientSmsNotification(
        array      $templateData,
        int        $resellerId,
        array      $differences,
        Contractor $client
    ): array {
        if (empty($client->mobile)) {
            return [false, []];
        }

        // Что такое $error? Массив для ошибок?
        $error = [];
        $result = NotificationManager::send(
            $resellerId,
            $client->id,
            NotificationEvents::CHANGE_RETURN_STATUS,
            (int)$differences['to'],
            $templateData,
            $error
        );
        return [(bool)$result, $error];
    }

    private function initEmptyResultArray(): array
    {
        return [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];
    }
}
