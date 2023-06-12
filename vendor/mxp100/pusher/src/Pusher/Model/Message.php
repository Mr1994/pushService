<?php
/**
 * Created by PhpStorm.
 * User: yuriy
 * Date: 3/17/17
 * Time: 2:40 PM
 */

namespace Pusher\Model;


class Message implements MessageInterface
{
    protected $text;
    protected $title;
    protected $priority;
    protected $ttl;
    protected $push_params;

    /**
     * Message constructor.
     *
     * @param  string  $text  Message
     * @param  string  $title  Title of message
     * @param  int  $priority  Message priority
     * @param  int  $ttl  Message TTL
     */
    public function __construct($push_message) {
        $this->title = $push_message->title;
        $this->text = $push_message->notification;
        $this->priority = 10;
        $this->ttl = 3600;
        $this->push_params = $push_message->push_params;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPriority(): int
    {
        return (int)$this->priority;
    }

    public function setTTL(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function getTTL(): int
    {
        return $this->ttl;
    }
    public function getPushParams()
    {
        return $this->push_params;
    }
}