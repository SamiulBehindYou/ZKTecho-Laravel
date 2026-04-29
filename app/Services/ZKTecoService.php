<?php

namespace App\Services;

use Jmrashed\Zkteco\Lib\ZKTeco;

class ZKTecoService
{
    protected ZKTeco $zk;

    public function __construct()
    {
        $this->zk = new ZKTeco(
            config('zkteco.ip'),
            config('zkteco.port')
        );
    }

    public function connect(): bool
    {
        return $this->zk->connect();
    }

    public function disconnect(): bool
    {
        return $this->zk->disconnect();
    }

    public function device(): ZKTeco
    {
        return $this->zk;
    }

    public function getInfo(): array
    {
        return [
            'version' => $this->zk->version(),
            'os_version' => $this->zk->osVersion(),
            'platform' => $this->zk->platform(),
            'firmware_version' => $this->zk->fmVersion(),
            'serial_number' => $this->zk->serialNumber(),
            'device_name' => $this->zk->deviceName(),
            'work_code' => $this->zk->workCode(),
            'time' => $this->zk->getTime(),
        ];
    }

    public function getUsers(): array
    {
        return $this->zk->getUser();
    }

    public function setUser(int $uid, int|string $userid, string $name, int|string $password, int $role = 0, int $cardno = 0): bool|mixed
    {
        return $this->zk->setUser($uid, $userid, $name, $password, $role, $cardno);
    }

    public function removeUser(int $uid): bool|mixed
    {
        return $this->zk->removeUser($uid);
    }

    public function clearUsers(): bool|mixed
    {
        return $this->zk->clearUsers();
    }

    public function clearAdmin(): bool|mixed
    {
        return $this->zk->clearAdmin();
    }

    public function getAttendance(): array
    {
        return $this->zk->getAttendance();
    }

    public function clearAttendance(): bool|mixed
    {
        return $this->zk->clearAttendance();
    }

    public function setTime(string $time): bool|mixed
    {
        return $this->zk->setTime($time);
    }

    public function getTime(): bool|mixed
    {
        return $this->zk->getTime();
    }

    public function restart(): bool|mixed
    {
        return $this->zk->restart();
    }

    public function shutdown(): bool|mixed
    {
        return $this->zk->shutdown();
    }

    public function testVoice(): bool|mixed
    {
        return $this->zk->testVoice();
    }

    public function getFingerprint(int $uid): array
    {
        return $this->zk->getFingerprint($uid);
    }

    public function getFaceData(int $uid): array
    {
        return $this->zk->getFaceData($uid);
    }

    public function getUserCardNumber(int $uid): string|false
    {
        return $this->zk->getUserCardNumber($uid);
    }
}
