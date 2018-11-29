<?php

/**
 * @copyright Copyright (C) Onaxis. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Onaxis\EzPlatformExtraBundle\Command;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\UserService;
use EzSystems\RepositoryForms\Data\Mapper\UserUpdateMapper;
use EzSystems\RepositoryForms\Form\Type\User\UserUpdateType;
use Onaxis\EzPlatformExtraBundle\Controller\UserController;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactory;

class UserSelfEditFormFieldsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ezplatform-extra:user-self-edit:form-fields')
            ->setDescription('List user self edit form fields to help you configuring your application.')
            ->setHelp('List user self edit form fields to help you configuring your application.')
            ->addArgument('user_content_id', InputArgument::OPTIONAL, 'Existing User Content ID (Admin user by default)', 14);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user_content_id = $input->getArgument('user_content_id');

        $output->writeln([
            '',
            '============================',
            'User Self Edit / Form fields',
            '============================',
            sprintf('User Content ID:    %s', $user_content_id)
        ]);

        /** @var Repository $repository */
        $repository = $this->getContainer()->get( 'ezpublish.api.repository' );

        /** @var ContentTypeService $contentTypeService */
        $contentTypeService = $repository->getContentTypeService();

        /** @var UserService $userService */
        $userService = $repository->getUserService();

        /** @var FormFactory $formFactory */
        $formFactory = $this->getContainer()->get('form.factory');

        /** @var UserController $service */
        $userController = $this->getContainer()->get('Onaxis\EzPlatformExtraBundle\Controller\UserController');

        // --

        $user = $userService->loadUser($user_content_id);

        $contentType = $contentTypeService->loadContentType($user->contentInfo->contentTypeId);

        $output->writeln(sprintf('Content Type ID:    %s', $user->contentInfo->contentTypeId));
        $output->writeln(sprintf('Content Identifier: %s', $contentType->identifier));

        $userUpdate = (new UserUpdateMapper())->mapToFormData($user, $contentType, ['languageCode' => null]);
        $form = $formFactory->create(
            UserUpdateType::class,
            $userUpdate,
            [
                'languageCode' => null,
                'mainLanguageCode' => null,
            ]
        );

        $available_fields = $userController->getAvailableFields($form);

        // --

        $output->writeln([
            '----------------------------',
            '',
        ]);

        foreach($available_fields as $field){
            $output->writeln(sprintf(' - %s', $field));
        }

        $output->writeln([
            '',
            '----------------------------',
            'Tips:',
            ' - You can use these identifiers to customize one or more user self edit forms. (Example: /user/selfedit/profile)',
            ' - You can pass as parameter of this command a User Content ID to list fields of another content type.',
            '============================',
        ]);
    }
}