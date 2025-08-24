<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Entities\Course;
use App\Entities\CourseCategory;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $courseRepository = $this->entityManager->getRepository(Course::class);
            $queryBuilder = $courseRepository->createQueryBuilder('c')
                ->leftJoin('c.category', 'cat')
                ->leftJoin('c.teachers', 't')
                ->leftJoin('t.teacher', 'teacher')
                ->where('c.isVisible = :visible')
                ->setParameter('visible', true);

            if ($request->has('category')) {
                $queryBuilder->andWhere('cat.id = :categoryId')
                    ->setParameter('categoryId', $request->category);
            }

            if ($request->has('search')) {
                $queryBuilder->andWhere('c.fullName LIKE :search OR c.shortName LIKE :search OR c.description LIKE :search')
                    ->setParameter('search', '%' . $request->search . '%');
            }

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $offset = ($page - 1) * $limit;

            $courses = $queryBuilder
                ->orderBy('c.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            $coursesData = array_map(function(Course $course) {
                return [
                    'id' => $course->getId(),
                    'fullName' => $course->getFullName(),
                    'shortName' => $course->getShortName(),
                    'code' => $course->getCode(),
                    'summary' => $course->summary,
                    'startDate' => $course->startDate->format('Y-m-d'),
                    'endDate' => $course->endDate?->format('Y-m-d'),
                    'enrollmentCount' => $course->getActiveEnrollmentCount(),
                    'category' => [
                        'id' => $course->getCategory()->getId(),
                        'name' => $course->getCategory()->getName()
                    ],
                    'thumbnailImage' => $course->thumbnailImage,
                    'language' => $course->language,
                    'credits' => $course->credits,
                    'tags' => $course->getTags()->map(function($tag) {
                        return ['id' => $tag->getId(), 'name' => $tag->getName()];
                    })->toArray()
                ];
            }, $courses);

            return response()->json([
                'success' => true,
                'courses' => $coursesData,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($courses)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $courseRepository = $this->entityManager->getRepository(Course::class);
            $course = $courseRepository->find($id);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            $user = $request->user();
            $isEnrolled = $user && $user->isEnrolledIn($course);
            $isTeacher = $user && $course->isUserTeacher($user);

            $courseData = [
                'id' => $course->getId(),
                'fullName' => $course->getFullName(),
                'shortName' => $course->getShortName(),
                'code' => $course->getCode(),
                'summary' => $course->summary,
                'description' => $course->description,
                'format' => $course->format,
                'startDate' => $course->startDate->format('Y-m-d'),
                'endDate' => $course->endDate?->format('Y-m-d'),
                'isVisible' => $course->isVisible,
                'enrollmentEnabled' => $course->enrollmentEnabled,
                'guestAccess' => $course->guestAccess,
                'maxStudents' => $course->maxStudents,
                'completionTracking' => $course->completionTracking,
                'thumbnailImage' => $course->thumbnailImage,
                'language' => $course->language,
                'credits' => $course->credits,
                'enrollmentCount' => $course->getActiveEnrollmentCount(),
                'isEnrolled' => $isEnrolled,
                'isTeacher' => $isTeacher,
                'category' => [
                    'id' => $course->getCategory()->getId(),
                    'name' => $course->getCategory()->getName()
                ],
                'teachers' => $course->getTeachers()->map(function($courseTeacher) {
                    return [
                        'id' => $courseTeacher->getTeacher()->getId(),
                        'fullName' => $courseTeacher->getTeacher()->getFullName(),
                        'role' => $courseTeacher->getRole()
                    ];
                })->toArray(),
                'sections' => $course->getSections()->map(function($section) {
                    return [
                        'id' => $section->getId(),
                        'name' => $section->getName(),
                        'summary' => $section->summary,
                        'position' => $section->position,
                        'isVisible' => $section->isVisible,
                        'activitiesCount' => $section->getActivities()->count()
                    ];
                })->toArray(),
                'tags' => $course->getTags()->map(function($tag) {
                    return ['id' => $tag->getId(), 'name' => $tag->getName()];
                })->toArray()
            ];

            return response()->json([
                'success' => true,
                'course' => $courseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch course',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'shortName' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:courses,code',
            'categoryId' => 'required|integer|exists:course_categories,id',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after:startDate',
            'maxStudents' => 'integer|min:0',
            'credits' => 'integer|min:0',
            'language' => 'string|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $categoryRepository = $this->entityManager->getRepository(CourseCategory::class);
            $category = $categoryRepository->find($request->categoryId);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }

            $course = new Course();
            $course->setFullName($request->fullName);
            $course->setShortName($request->shortName);
            $course->setCode($request->code);
            $course->setCategory($category);
            $course->summary = $request->summary;
            $course->description = $request->description;
            $course->startDate = new \DateTime($request->startDate);
            
            if ($request->endDate) {
                $course->endDate = new \DateTime($request->endDate);
            }
            
            if ($request->has('maxStudents')) {
                $course->maxStudents = $request->maxStudents;
            }
            
            if ($request->has('credits')) {
                $course->credits = $request->credits;
            }
            
            if ($request->has('language')) {
                $course->language = $request->language;
            }

            $this->entityManager->persist($course);
            
            $user = $request->user();
            if ($user) {
                $course->addTeacher($user, 'manager');
            }

            $this->entityManager->flush();

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully',
                'course' => [
                    'id' => $course->getId(),
                    'fullName' => $course->getFullName(),
                    'shortName' => $course->getShortName(),
                    'code' => $course->getCode()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Course creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}