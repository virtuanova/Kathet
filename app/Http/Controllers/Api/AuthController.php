<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = new User();
            $user->setUsername($request->username);
            $user->setEmail($request->email);
            $user->setFirstName($request->firstName);
            $user->setLastName($request->lastName);
            $user->password = Hash::make($request->password);
            
            if ($request->phone) {
                $user->phone = $request->phone;
            }
            
            if ($request->city) {
                $user->city = $request->city;
            }
            
            if ($request->country) {
                $user->country = $request->country;
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            
            $user = $userRepository->createQueryBuilder('u')
                ->where('u.username = :login OR u.email = :login')
                ->andWhere('u.isActive = :active')
                ->setParameter('login', $request->login)
                ->setParameter('active', true)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user->lastLogin = new \DateTime();
            $user->lastLoginIp = $request->ip();
            $this->entityManager->flush();

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'roles' => $user->getRoles()->map(function($role) {
                        return [
                            'id' => $role->getId(),
                            'name' => $role->getName(),
                            'displayName' => $role->getDisplayName()
                        ];
                    })->toArray()
                ],
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'phone' => $user->phone,
                    'city' => $user->city,
                    'country' => $user->country,
                    'language' => $user->language,
                    'timezone' => $user->timezone,
                    'isActive' => $user->isActive,
                    'emailVerified' => $user->emailVerified,
                    'lastLogin' => $user->lastLogin?->format('Y-m-d H:i:s'),
                    'roles' => $user->getRoles()->map(function($role) {
                        return [
                            'id' => $role->getId(),
                            'name' => $role->getName(),
                            'displayName' => $role->getDisplayName()
                        ];
                    })->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}