<?php

namespace ggg\SendTrigger;

use ggg\Apl\Apl;
use ggg\CommonEntity\CommonEntity;

class SendTrigger extends CommonEntity
{

    /**
     * Возвращает данные условия отправки триггера
     * @return array
     */
    function get_condition_data(): array
    {
        if ($this->data['condition_data']){
            return json_decode($this->data['condition_data'], true);
        }else {
            return array();
        }

    }

    /**
     * Возвращает статус  триггера
     * @return string
     */
    function get_condition_status(): string
    {
        return $this->data['condition_status'];
    }

    /**
     * Возвращает данные события
     * @return array
     */
    function get_event_data(): array
    {
        return json_decode($this->data['event_data'], true);
    }

    /**
     * Возвращает тип почтового события
     * @return string
     */
    function get_event_type(): string
    {
        return $this->data['event_type'];
    }

    /**
     * Возвращает время когда должно быть отработано
     * @return \DateTime
     * @throws \Exception
     */
    function get_send_time(): \DateTime
    {
        return new \DateTime($this->data['send_time']);
    }

    /**
     * Устанавливает статус отменен
     * @return void
     */
    function set_canceled(): void
    {
        $this->set('condition_status', 'canceled');
    }

    /**
     * Устанавливает статус отправвлено
     * @return void
     */
    function set_sent(): void
    {
        $this->set('condition_status', 'sent');
    }

    /**
     * Проверка триггера для отправки
     * @return bool
     */
    function check_condition(): bool
    {
        if ($this->data['condition_class']){
            $condition = new $this->data['condition_class'];
            return $condition->send($this->get_condition_data());
        }else {
            return true;
        }
    }

    /**
     * Выполняет отправку триггера
     * @return void
     */
    function send(): void
    {
        if ($this->check_condition()){
            \CEvent::Send($this->get_event_type(),'s1', $this->get_event_data());
            $this->set_sent();
        }else{
            $this->set_canceled();
        }
    }

    /**
     * Создает триггер
     * @param string $event_type тип почтового события
     * @param array $event_data Массив должен содержать данные которые будут использоваться при создании почтового события
     * @param \DateTime $send_time
     * @param array $condition_data Массив должен содержать данные для проверки события, обязательное поле entity_id = id сущности для проверки (например id клиента)
     * @param SendTriggerCondition|null $condition_class объект проверки триггера
     * @return void
     */
    static function add_trigger(string $event_type, array $event_data, \DateTime $send_time, array $condition_data = array(), SendTriggerCondition $condition_class = null): void
    {
        $time = $send_time->format('d.m.Y H:i:s');
        $trigger = self::create();
        $trigger->set('event_type', $event_type);
        $trigger->set('event_data', json_encode($event_data, JSON_UNESCAPED_UNICODE));
        $trigger->set('send_time', $time);
        $trigger->set('condition_status', 'pending');
        if ($condition_data){
            $trigger->set('condition_data', json_encode($condition_data, JSON_UNESCAPED_UNICODE));
        }
        if ($condition_class){
            $trigger->set('condition_class', get_class($condition_class));
        }
    }

    public static function get_table_name(): string
    {
        return 'trigger_send_events';
    }

    /**
     * Получает триггеры по текущей дате
     * @return \Generator
     */
    static function get_trigger(\DateTime $send_time = null): \Generator
    {
        if (!$send_time){
            $datetime = new \DateTime();
        }else {
            $datetime = $send_time;
        }
        $table = self::get_table_name();
        $DB = Apl::get_instance()->get_db_instance();
        $query = sprintf("SELECT * FROM %s WHERE send_time < '%s' AND condition_status = 'pending'", $table, $datetime->format('Y-m-d H:i:s'));
        $db_res = $DB->Query($query);
        while ($res = $db_res->Fetch()) {
            yield new self($res);
        }
    }

}
