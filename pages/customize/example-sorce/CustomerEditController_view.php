<?php

namespace Eccube\Controller\Admin\Customer;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomerEditController extends AbstractController
{
    public function index(Application $app, Request $request, $id = null)
    {
        // 編集
        if ($id) {
            $Customer = $app['orm.em']
                ->getRepository('Eccube\Entity\Customer')
                ->find($id);

            if (is_null($Customer)) {
                throw new NotFoundHttpException();
            }
            // 編集用にデフォルトパスワードをセット
            $previous_password = $Customer->getPassword();
            $Customer->setPassword($app['config']['default_password']);
            // 新規登録
        } else {
            $Customer = $app['eccube.repository.customer']->newCustomer();
            $CustomerAddress = new \Eccube\Entity\CustomerAddress();
            $Customer->setBuyTimes(0);
            $Customer->setBuyTotal(0);
        }

        // 会員登録フォーム
        $builder = $app['form.factory']
            ->createBuilder('admin_customer', $Customer);

        $event = new EventArgs(
            array(
                'builder' => $builder,
                'Customer' => $Customer,
            ),
            $request
        );
        $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CUSTOMER_EDIT_INDEX_INITIALIZE, $event);

        $form = $builder->getForm();

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                if ($Customer->getId() === null) {
                    $Customer->setSalt(
                        $app['eccube.repository.customer']->createSalt(5)
                    );
                    $Customer->setSecretKey(
                        $app['eccube.repository.customer']->getUniqueSecretKey($app)
                    );

                    $CustomerAddress->setName01($Customer->getName01())
                        ->setName02($Customer->getName02())
                        ->setKana01($Customer->getKana01())
                        ->setKana02($Customer->getKana02())
                        ->setCompanyName($Customer->getCompanyName())
                        ->setZip01($Customer->getZip01())
                        ->setZip02($Customer->getZip02())
                        ->setZipcode($Customer->getZip01() . $Customer->getZip02())
                        ->setPref($Customer->getPref())
                        ->setAddr01($Customer->getAddr01())
                        ->setAddr02($Customer->getAddr02())
                        ->setTel01($Customer->getTel01())
                        ->setTel02($Customer->getTel02())
                        ->setTel03($Customer->getTel03())
                        ->setFax01($Customer->getFax01())
                        ->setFax02($Customer->getFax02())
                        ->setFax03($Customer->getFax03())
                        ->setDelFlg(Constant::DISABLED)
                        ->setCustomer($Customer);

                    $app['orm.em']->persist($CustomerAddress);
                }

                if ($Customer->getPassword() === $app['config']['default_password']) {
                    $Customer->setPassword($previous_password);
                } else {
                    if ($Customer->getSalt() === null) {
                        $Customer->setSalt($app['eccube.repository.customer']->createSalt(5));
                    }
                    $Customer->setPassword(
                        $app['eccube.repository.customer']->encryptPassword($app, $Customer)
                    );
                }

                $app['orm.em']->persist($Customer);

                $app['orm.em']->flush();

                $event = new EventArgs(
                    array(
                        'form' => $form,
                        'Customer' => $Customer,
                    ),
                    $request
                );
                $app['eccube.event.dispatcher']->dispatch(EccubeEvents::ADMIN_CUSTOMER_EDIT_INDEX_COMPLETE, $event);

                $app->addSuccess('admin.customer.save.complete', 'admin');

                return $app->redirect($app->url('admin_customer_edit', array(
                    'id' => $Customer->getId(),
                )));
            } else {
                $app->addError('admin.customer.save.failed', 'admin');
            }
        }

        return $app->render('Customer/edit.twig', array(
            'form' => $form->createView(),
            'Customer' => $Customer,
        ));
    }
}
