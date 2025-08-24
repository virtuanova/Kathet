<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Entities\Course;
use App\Entities\Enrollment;
use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function enroll(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();
            $courseRepository = $this->entityManager->getRepository(Course::class);
            $course = $courseRepository->find($courseId);

            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found'
                ], 404);
            }

            if (!$course->enrollmentEnabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enrollment is not enabled for this course'
                ], 400);
            }

            if ($user->isEnrolledIn($course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already enrolled in this course'
                ], 400);
            }

            if ($course->maxStudents > 0 && $course->getActiveEnrollmentCount() >= $course->maxStudents) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course is full'
                ], 400);
            }

            $enrollment = new Enrollment();
            $enrollment->setUser($user);
            $enrollment->setCourse($course);
            $enrollment->setStatus('active');

            $this->entityManager->persist($enrollment);
            $this->entityManager->flush();

            return response()->json([
                'success' => true,
                'message' => 'Successfully enrolled in course',
                'enrollment' => [
                    'id' => $enrollment->getId(),
                    'status' => $enrollment->getStatus(),
                    'enrolledAt' => $enrollment->enrolledAt->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Enrollment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function unenroll(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $enrollmentRepository = $this->entityManager->getRepository(Enrollment::class);
            $enrollment = $enrollmentRepository->findOneBy([
                'user' => $user,
                'course' => $courseId,
                'status' => 'active'
            ]);

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enrolled in this course'
                ], 404);
            }

            $enrollment->setStatus('unenrolled');
            $this->entityManager->flush();

            return response()->json([
                'success' => true,
                'message' => 'Successfully unenrolled from course'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unenrollment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myEnrollments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $enrollmentRepository = $this->entityManager->getRepository(Enrollment::class);
            $enrollments = $enrollmentRepository->createQueryBuilder('e')
                ->leftJoin('e.course', 'c')
                ->leftJoin('c.category', 'cat')
                ->where('e.user = :user')
                ->andWhere('e.status = :status')
                ->setParameter('user', $user)
                ->setParameter('status', 'active')
                ->orderBy('e.enrolledAt', 'DESC')
                ->getQuery()
                ->getResult();

            $enrollmentsData = array_map(function(Enrollment $enrollment) {
                $course = $enrollment->getCourse();
                return [
                    'id' => $enrollment->getId(),
                    'status' => $enrollment->getStatus(),
                    'progress' => $enrollment->getProgress(),
                    'finalGrade' => $enrollment->getFinalGrade(),
                    'enrolledAt' => $enrollment->enrolledAt->format('Y-m-d H:i:s'),
                    'completedAt' => $enrollment->completedAt?->format('Y-m-d H:i:s'),
                    'course' => [
                        'id' => $course->getId(),
                        'fullName' => $course->getFullName(),
                        'shortName' => $course->getShortName(),
                        'code' => $course->getCode(),
                        'summary' => $course->summary,
                        'thumbnailImage' => $course->thumbnailImage,
                        'category' => [
                            'id' => $course->getCategory()->getId(),
                            'name' => $course->getCategory()->getName()
                        ]
                    ]
                ];
            }, $enrollments);

            return response()->json([
                'success' => true,
                'enrollments' => $enrollmentsData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollments',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}