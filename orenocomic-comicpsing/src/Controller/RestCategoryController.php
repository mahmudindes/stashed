<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Repository\CategoryKindRepository;
use App\Util\QueryParameter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\UnicodeString;

#[Route("/api/rest/v0/categories", name: 'rest_category_')]
class RestCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryKindRepository $categoryKindRepository
    )
    {
    }

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(
        Request $request,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => 15])] int $limit = 10,
        #[MapQueryParameter] string $sort = null
    ): Response
    {
        $queries = new QueryParameter($request->server);

        $criteria = [];
        $orderBys = $queries->all('order_by');
        $offset = $limit * ($page - 1);

        if ($sort) \array_unshift($orderBys, 'typeCode sort=' . $sort, 'code sort=' . $sort);

        $result = $this->categoryRepository->findByCustom($criteria, $orderBys, $limit, $offset);
        $totalCount = $this->categoryRepository->countCustom($criteria);

        return $this->json($result, Response::HTTP_OK, [
            'X-Total-Count' => $totalCount,
            'X-Pagination-Limit' => $limit,
        ]);
    }

    #[Route('', name: 'post', methods: [Request::METHOD_POST])]
    public function post(
        Request $request
    ): Response
    {
        $entity = new Category();

        $form = $this->createForm(CategoryType::class, $entity, ['csrf_protection' => false]);
        switch ($request->headers->get('Content-Type')) {
            case 'application/json':
                $form->submit(\json_decode($request->getContent(), true));
                break;
            default:
                $form->submit($request->getPayload()->all());
        }
        if (!$form->isValid()) {
            return $this->json(['message' => 'Category data is not valid.'], Response::HTTP_BAD_REQUEST);
        }

        $entity = $form->getData();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $typecodecode = $entity->getType()->getCode() . ':' . $entity->getCode();
        $slug = $request->getPathInfo() . '/' . $typecodecode;
        return $this->json($entity, Response::HTTP_CREATED, ['Location' => $slug]);
    }

    #[Route('/{typeCode}:{code}', name: 'get_by_typecodecode', methods: [Request::METHOD_GET])]
    public function getByCode(
        string $typeCode, string $code
    ): Response
    {
        $result = $this->categoryRepository->findOneBy([
            'type' => $this->categoryKindRepository->findOneBy(['code' => $typeCode]),
            'code' => $code,
        ]);
        if (!$result) {
            return $this->json(['message' => 'Category does not exists.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    #[Route('/{typeCode}:{code}', name: 'patch_by_typecodecode', methods: [Request::METHOD_PATCH])]
    public function patchByCode(
        Request $request, string $typeCode, string $code
    ): Response
    {
        $result = $this->categoryRepository->findOneBy([
            'type' => $this->categoryKindRepository->findOneBy(['code' => $typeCode]),
            'code' => $code,
        ]);
        if (!$entity) {
            return $this->json(['message' => 'Category does not exists.'], Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(CategoryType::class, $entity, ['csrf_protection' => false]);
        switch ($request->headers->get('Content-Type')) {
            case 'application/json':
                $form->submit(\json_decode($request->getContent(), true), false);
                break;
            default:
                $form->submit($request->getPayload()->all(), false);
        }
        if (!$form->isValid()) {
            return $this->json(['message' => 'Category data is not valid.'], Response::HTTP_BAD_REQUEST);
        }

        $entity = $form->getData();

        $this->entityManager->flush();

        $typecodecodeOld = $typeCode . ':' . $code;
        $typecodecodeNew = $entity->getType()->getCode() . ':' . $entity->getCode();
        $slug = (new UnicodeString($request->getPathInfo()))->trimSuffix($typecodecodeOld) . $typecodecodeNew;
        return $this->json($entity, Response::HTTP_OK, ['Location' => $slug]);
    }

    #[Route('/{typeCode}:{code}', name: 'delete_by_typecodecode', methods: [Request::METHOD_DELETE])]
    public function deleteByCode(
        string $typeCode, string $code
    ): Response
    {
        $result = $this->categoryRepository->findOneBy([
            'type' => $this->categoryKindRepository->findOneBy(['code' => $typeCode]),
            'code' => $code,
        ]);
        if (!$entity) {
            return $this->json(['message' => 'Category does not exists.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
