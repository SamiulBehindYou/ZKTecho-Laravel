<?php

namespace App\Http\Controllers;

use App\Services\ZKTecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZKTecoController extends Controller
{
    public function __construct(
        protected ZKTecoService $zkteco
    ) {}

    public function connect(): JsonResponse
    {
        $connected = $this->zkteco->connect();

        if (!$connected) {
            return response()->json(['success' => false, 'message' => 'Failed to connect to device'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Connected successfully']);
    }

    public function disconnect(): JsonResponse
    {
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'message' => 'Disconnected']);
    }

    public function info(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $info = $this->zkteco->getInfo();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $info]);
    }

    public function users(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $users = $this->zkteco->getUsers();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function setUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uid' => 'required|integer',
            'userid' => 'required',
            'name' => 'required|string|max:24',
            'password' => 'required',
            'role' => 'integer|default:0',
            'cardno' => 'integer|default:0',
        ]);

        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->setUser(
            $validated['uid'],
            $validated['userid'],
            $validated['name'],
            $validated['password'],
            $validated['role'],
            $validated['cardno']
        );
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function removeUser(int $uid): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->removeUser($uid);
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function attendance(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $attendance = $this->zkteco->getAttendance();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function clearAttendance(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->clearAttendance();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function getTime(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $time = $this->zkteco->getTime();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $time]);
    }

    public function setTime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'time' => 'required|date_format:Y-m-d H:i:s',
        ]);

        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->setTime($validated['time']);
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function restart(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->restart();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function testVoice(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->testVoice();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function clearUsers(): JsonResponse
    {
        if (!$this->zkteco->connect()) {
            return response()->json(['success' => false, 'message' => 'Failed to connect'], 500);
        }

        $result = $this->zkteco->clearUsers();
        $this->zkteco->disconnect();

        return response()->json(['success' => true, 'data' => $result]);
    }
}
