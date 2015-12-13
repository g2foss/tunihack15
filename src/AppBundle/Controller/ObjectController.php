<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Event;
use AppBundle\Entity\Field;
use AppBundle\Entity\Object;
use AppBundle\Form\EventType;
use AppBundle\Form\ObjectInspectType;
use AppBundle\Form\ObjectType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ObjectController extends Controller
{
    /**
     * @Route("/new", name="new")
     */
    public function newAction(Request $request)
    {
        $object = new Object();
        $form = $this->createForm(new ObjectType(), $object);
        $form->add('submit', 'submit');
        $form->handleRequest($request);
        if($form->isValid()){
            $date = new \DateTime();
            if($date < $object->getDeadline()) {
                $em = $this->getDoctrine()->getEntityManager();
                $salt = $this->container->getParameter('secret');
                $object->setPrivateKey(sha1($date->getTimestamp()));
                $object->setPublicKey(sha1($object->getPrivateKey() . $salt));
                $object->setCreatedAt($date);
                foreach($object->getFields() as $field)
                    $field->setObject($object);
                $em->persist($object);
                $em->flush();
                return $this->render('AppBundle:Object:validate.html.twig', ['private' => $object->getPrivateKey(), 'public' => $object->getPublicKey()]);
            }
            else {
                foreach($object->getFields() as $field){
                    $object->removeField($field);
                }
                $this->addFlash('error', 'Error');
            }
        }else {
            foreach($object->getFields() as $field){
                $object->removeField($field);
            }
            $this->addFlash('error', 'Error');
        }
        return $this->render('AppBundle:Object:new.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/view/{id}", name="object_view")
     */
    public function viewAction(Object $object, Request $request)
    {
        $event = new Event();
        $form = $this->createForm(new EventType(), $event);
        $form->add('submit', 'submit');
        $form->handleRequest($request);

        if($form->isValid()){
            $em = $this->getDoctrine()->getEntityManager();
            $event->setPrivateKeyObject($object->getPrivateKey());
            $event->setPrivateKeyUser($this->getRequest()->getSession()->get('user')->getPrivateKey());
            $event->setCreatedAt(new \DateTime());
            $em->persist($event);
            $em->flush();
            return $this->redirectToRoute('submissions');
        }

        return $this->render('AppBundle:Object:view.html.twig', array(
            'object' => $object,
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/list", name="list")
     */
    public function listAction()
    {
        $objects = $this->getDoctrine()->getRepository('AppBundle:Object')->getAll();

        return $this->render('AppBundle:Object:list.html.twig', array(
            'objects' => $objects
        ));
    }

    /**
     * @Route("/inspect", name="inspect")
     */
    public function inspectAction(Request $request)
    {
        $object = new Object();
        $form = $this->createForm(new ObjectInspectType(), $object);
        $form->add('submit', 'submit');

        $form->handleRequest($request);
        if($form->isValid()){
            $objectLogged = $this->getDoctrine()->getRepository('AppBundle:Object')->findOneBy(['privateKey' => $object->getPrivateKey(), 'publicKey' => $object->getPublicKey()]);
            if($objectLogged) {
                $events = $this->getDoctrine()->getRepository('AppBundle:Event')->findBy(['privateKeyObject' => $object->getPrivateKey()]);
                // dump($events);
                $id = null;
                $min = null;
                foreach ($events as $i => $event) {
//                    dump($event);die();
                    $s = 0;
                    foreach ($event->getValues() as $j => $value)
                        $s += $value * $objectLogged->getFields()[$j]->getQuantity();
                    if ($min == null || $min > $s) {
                        $min = $s;
                        $id = $i;
                    }
                }
                if ($id) {

                $user = $this->getDoctrine()->getRepository('AppBundle:User')->findOneByPrivateKey($events[$id]->getPrivateKeyUser());
                return $this->render('AppBundle:Object:inspect.html.twig', array('user' => $user, 'object' => $objectLogged, 'event' => $events[$id], 'montant' => $min));
            }else return $this->render('AppBundle:Object:inspect.html.twig', array('user' => null, 'object' => $objectLogged, 'event' => null, 'events'=>$events, 'montant' => 0));

            }
        }

        return $this->render('AppBundle:Object:inspectForm.html.twig', array(
            'form' => $form->createView()
        ));
    }
    /**
     * @Route("/submissions", name="submissions")
     */
    public function submissionsAction(Request $request){
        $session = $this->getRequest()->getSession();
        if($session->has('user')){
            $user = $session->get('user');
            $events = $this->getDoctrine()->getRepository('AppBundle:Event')->findByPrivateKeyUser($user->getPrivateKey());
            $objects = array();
            foreach ($events as $event) {
                $object = $this->getDoctrine()->getRepository('AppBundle:Object')->getObjectByPrivateKey($event->getPrivateKeyObject());
                $objects[] = $object;
            }
            return $this->render('AppBundle:Object:list.html.twig', array(
                'events' => $events, 'objects'=>$objects
            ));
        }else{
            return $this->redirectToRoute('login');
        }
    }
}
