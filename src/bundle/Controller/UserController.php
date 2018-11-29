<?php

/**
 * @copyright Copyright (C) Onaxis. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Onaxis\EzPlatformExtraBundle\Controller;

use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\Base\Exceptions\UnauthorizedException as CoreUnauthorizedException;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use EzSystems\RepositoryForms\Form\Type\User\UserUpdateType;
use EzSystems\RepositoryForms\Data\Mapper\UserUpdateMapper;
use EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface;
use EzSystems\RepositoryForms\User\View\UserUpdateView;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserController extends Controller
{
    /** @var ContentTypeService */
    private $contentTypeService;

    /** @var \eZ\Publish\API\Repository\Repository */
    protected $repository;

    /** @var UserService */
    private $userService;

    /** @var \eZ\Publish\API\Repository\PermissionResolver */
    private $permissionResolver;

    /** @var ActionDispatcherInterface */
    private $userActionDispatcher;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var array */
    private $filters_config;

    private const FILTER_NONE = 'none';
    private const FILTER_TYPE_INCLUDE_EXCLUDE = 'include/exclude';
    private const FILTER_TYPE_EXCLUDE_INCLUDE = 'exclude/include';

    public function __construct(
        ContentTypeService $contentTypeService,
        PermissionResolver $permissionResolver,
        UserService $userService,
        Repository $repository,
        ActionDispatcherInterface $userActionDispatcher,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->contentTypeService = $contentTypeService;
        $this->permissionResolver = $permissionResolver;
        $this->repository = $repository;
        $this->userService = $userService;
        $this->userActionDispatcher = $userActionDispatcher;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Renders the URLs list.
     *
     * @param Request $request
     *
     * @param string $filter
     * @return UserUpdateView|Response
     * @throws CoreUnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function selfEditAction(Request $request, $filter = self::FILTER_NONE)
    {
        $user_ref = $this->permissionResolver->getCurrentUserReference();
        $user = $this->userService->loadUser($user_ref->getUserId());

        if (!$this->permissionResolver->canUser('user', 'selfedit', $user)){
            throw new CoreUnauthorizedException('user', 'selfedit');
        }

        $contentType = $this->contentTypeService->loadContentType($user->contentInfo->contentTypeId);
        $language = $user->contentInfo->mainLanguageCode;

        $userUpdate = (new UserUpdateMapper())->mapToFormData($user, $contentType, [
            'languageCode' => $language,
        ]);
        $form = $this->createForm(
            UserUpdateType::class,
            $userUpdate,
            [
                'languageCode' => $language,
                'mainLanguageCode' => $language,
            ]
        );

        $this->filterFields($form, $filter);

        $form['redirectUrlAfterPublish']->setData($this->urlGenerator->generate(
            'onaxis_ezplatform_extra.user.selfedit', ['filter' => $filter], UrlGeneratorInterface::ABSOLUTE_URL
        ));

        // we alwayse remove the ezuser/enabled field from form
        foreach($form->get('fieldsData') as $name => $field){
            /** @var \Symfony\Component\Form\FormBuilderInterface $field */
            if ($field->get('value')->getConfig()->getType()->getBlockPrefix() != 'ezplatform_fieldtype_ezuser') continue;

            $field->get('value')->remove('enabled');
        }

        $available_fields = $this->getAvailableFields($form);

        //echo '<pre>';
        //print_r($available_fields);
        //exit;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $form->getClickedButton()) {

            $this->repository->sudo(function (Repository $repository) use ($form, $userUpdate ) {
                $this->userActionDispatcher->dispatchFormAction($form, $userUpdate, $form->getClickedButton()->getName());
            });

            if ($response = $this->userActionDispatcher->getResponse()) {
                return $response;
            }
        }

        return new UserUpdateView(null, [
            'form' => $form->createView(),
            'languageCode' => $language,
            'contentType' => $contentType,
            'user' => $user,
        ]);
    }

    /**
     * Field name example:
     *
     * - firstname
     * - lastname
     * - user_account
     * - user_account.username
     * - user_account.password
     * - user_account.password.first
     * - user_account.password.second
     * - user_account.email
     * - user_account.enabled
     *
     * @param \Symfony\Component\Form\FormInterface|\Symfony\Component\Form\FormInterface[]|\Symfony\Component\Form\FormBuilderInterface[] $fields
     * @param string $prepend
     * @return array
     */
    public function getAvailableFields($fields, $prepend = ''){

        if ($fields instanceof \Symfony\Component\Form\Form){
            return $this->getAvailableFields($fields->get('fieldsData')->all());
        }

        $return = [];

        foreach($fields as $key=>$value){
            $identifier = $prepend == '' ? $key : $prepend . '.' . $key;
            $return[] = preg_replace('/\.value/', '', $identifier);
            $return = array_merge($return, $this->getAvailableFields($value->all(), $identifier));
        }

        return array_unique($return);
    }

    public function setFiltersConfig( $filters_config )
    {
        $this->filters_config = $filters_config;
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form
     * @param string $filter
     * @throws InvalidArgumentException
     */
    private function filterFields(\Symfony\Component\Form\FormInterface $form, string $filter)
    {
        if ($filter !== self::FILTER_NONE){
            if (!array_key_exists($filter, $this->filters_config)){
                throw new InvalidArgumentException('filter', sprintf('Filter "%s" is not defined.', $filter));
            }

            $available_fields = $this->getAvailableFields($form);
            $fields_to_remove = [];

            switch($this->filters_config[$filter]['type']){

                case self::FILTER_TYPE_INCLUDE_EXCLUDE:

                    $fields_to_remove = array_merge($fields_to_remove, $this->getFieldsToRemoveByInclude($available_fields, $filter));
                    $fields_to_remove = array_merge($fields_to_remove, $this->getFieldsToRemoveByExlude($available_fields, $filter));

                    break;

                case self::FILTER_TYPE_EXCLUDE_INCLUDE:

                    $fields_to_remove = array_merge($fields_to_remove, $this->getFieldsToRemoveByExlude($available_fields, $filter));
                    $fields_to_remove = array_merge($fields_to_remove, $this->getFieldsToRemoveByInclude($available_fields, $filter));

                    break;
            }

            $this->removeFields($form, array_unique($fields_to_remove));
        }
    }

    private function getFieldsToRemoveByInclude($available_fields, $filter){

        $fields_to_remove = [];

        foreach ($available_fields as $available_field){
            $found = false;
            foreach($this->filters_config[$filter]['include'] as $include_pattern){
                //if (($filter === self::FILTER_TYPE_INCLUDE_EXCLUDE && $include_pattern === '*') || strpos($available_field, $include_pattern) === 0) {
                if ($include_pattern === '*' || strpos($available_field, $include_pattern) === 0) {
                    $found = true;
                }
            }
            if (!$found){
                $fields_to_remove[] = $available_field;
            }
        }
        return $fields_to_remove;
    }

    private function getFieldsToRemoveByExlude($available_fields, $filter){

        $fields_to_remove = [];

        foreach ($available_fields as $available_field){
            $found = false;
            foreach($this->filters_config[$filter]['exclude'] as $exclude_pattern){
                if ($available_field === $exclude_pattern){ //strpos($available_field, $exclude_pattern) === 0) {
                    $found = true;
                }
            }
            if ($found){
                $fields_to_remove[] = $available_field;
            }
        }

        return $fields_to_remove;
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form
     * @param array $fields_to_remove
     */
    private function removeFields(\Symfony\Component\Form\FormInterface $form, array $fields_to_remove)
    {
        /*
        echo '<pre>';
        print_r($fields_to_remove);
        exit;
        */

        foreach($fields_to_remove as $field_to_remove){
            $this->removeField($form, $field_to_remove);
        }
    }

    private function removeField(\Symfony\Component\Form\FormInterface $form, string $field_to_remove)
    {
        $form->setData(['attr' => ['test'=>'ok']]);
        $formInterface = $form->get('fieldsData');

        $field_to_remove = preg_replace('/\./', '.value.', $field_to_remove);
        $parts = preg_split('/\./', $field_to_remove);

        foreach($parts as $index=>$key){

            if (count($parts) === $index+1 ){
                if ($formInterface->has($key))
                    $formInterface->remove($key);
            }else{
                if ($formInterface->has($key))
                    $formInterface = $formInterface->get($key);
            }
        }

    }
}
