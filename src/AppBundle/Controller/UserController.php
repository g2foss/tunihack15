<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Form\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait Referer {
    private function getRefererParams() {
        $request = $this->getRequest();
        $referer = $request->headers->get('referer');
        $baseUrl = $request->getBaseUrl();
        $lastPath = substr($referer, strpos($referer, $baseUrl) + strlen($baseUrl));
        return $this->get('router')->getMatcher()->match($lastPath);
    }
}
class UserController extends Controller
{

    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request)
    {
        $session = $this->getRequest()->getSession();
        if($session->has('user')){
            return $this->redirectToRoute('home');
        }
        $user = new User();
        $form = $this->createForm(new UserType(), $user);
        $form->add('submit', 'submit');

        $form->handleRequest($request);
        if($form->isValid()){
            $userLogged = $this->getDoctrine()->getRepository('AppBundle:User')->findOneBy(['privateKey' => $user->getPrivateKey(), 'publicKey' => $user->getPublicKey()]);
            if($userLogged){
                $session->set('user', $userLogged);
                return $this->redirectToRoute('list');
            }
        }

        return $this->render('AppBundle:User:login.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/logout",name="logout")
     */
    public function logoutAction()
    {
        $session = $this->getRequest()->getSession();
        if($session->has('user')){
            $session->remove('user');
        }
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/signIn", name="signIn")
     */
    public function signInAction()
    {
        $em = $this->getDoctrine()->getEntityManager();
        $user = new User();
        $date = new \DateTime();
        $salt = $this->container->getParameter('secret');
        $user->setPrivateKey(sha1($date->getTimestamp()));
        $user->setPublicKey(sha1($user->getPrivateKey().$salt));
        $em->persist($user);
        $em->flush();
        return $this->render('AppBundle:User:signIn.html.twig', array(
            'user' => $user
        ));
    }

}
