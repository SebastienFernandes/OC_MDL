<?php

namespace PROJET\PlatformBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PROJET\PlatformBundle\Entity\Ticket;
use PROJET\PlatformBundle\Entity\Reservation;
use PROJET\PlatformBundle\Entity\TicketCount;
use PROJET\PlatformBundle\Form\TicketType;
use PROJET\PlatformBundle\Form\ReservationType;
use PROJET\PlatformBundle\Count\Check;
use PROJET\PlatformBundle\Count\Count;
use PROJET\PlatformBundle\SubmitForm\SubmitForm;
use PROJET\PlatformBundle\Billing\Billing;

class TicketController extends Controller
{
    public function indexAction($id)
    {
        $em             = $this->getDoctrine()->getManager();
        $reservation    = $em->getRepository('PROJETPlatformBundle:Reservation')->find($id);
        $apiEmail       = $reservation->getEmail();
        $serviceBilling = $this->get(Billing::class);
        $totalPrice     = $serviceBilling->calculateTotalPrice($reservation);

        $day = $reservation->getDate();
        $test = date_format( $day, "m/d");
        $test2 = "06/30";
        if ("06/30" === $test){
            var_dump("true");
        }

        return $this->render('PROJETPlatformBundle:Reservation:index.html.twig', array(
            'reservation' => $reservation,
            'price' => $totalPrice,
            'tickets' => $reservation->getTickets()
        ));
    }

    public function addAction(Request $request)
    {
        $em          = $this->getDoctrine()->getManager();
        $reservation = new Reservation();
        $form        = $this->get('form.factory')->create(ReservationType::class, $reservation);

        $serviceCheck     = $this->get(Check::class);
        $ticketCountToDay = $serviceCheck->checkTicketCountToDay($em);

        if (!$request->isXmlHttpRequest() && $form->isSubmitted() && $form->isValid()) {
            $serviceSubmitForm = $this->get(SubmitForm::class);
            $submitForm        = $serviceSubmitForm->submit($request, $em, $reservation, $form);

            switch ($submitForm) {
                case 0:
                    $request->getSession()->getFlashBag()->add('info', 'Le date de réservation n\'est pas valide');
                    return $this->redirectToRoute('projet_platform_add');
                    break;
                case 1:
                    $request->getSession()->getFlashBag()->add('info', 'Il n\'y a plus assé de places pour ce jour.');
                    return $this->redirectToRoute('projet_platform_add');
                    break;
                case 2:
                    return $this->redirectToRoute('projet_platform_home', array('id' => $reservation->getId()));
                    break;
                case 3:
                    $request->getSession()->getFlashBag()->add('info', 'Fermeture du musée les mardis, les 1er mai, 1er novembre et 25 décembre');
                    return $this->redirectToRoute('projet_platform_add');
                    break;
                case 4:
                    $request->getSession()->getFlashBag()->add('info', 'Pas de réservation sur l\'application les dimanches et jours fériés');
                    return $this->redirectToRoute('projet_platform_add');
                    break;
            }
        }

        if ($request->isXmlHttpRequest())
        {            
            $ticketCount = $serviceCheck->checkTicketCount($request, $em);
            return new JsonResponse($ticketCount);
        }        

        return $this->render('PROJETPlatformBundle:Reservation:add.html.twig', array(
          'form' => $form->createView(),
          'ticketCountToDay' => $ticketCountToDay
        ));
    }

    public function delAction($id)
    {
        $em             = $this->getDoctrine()->getManager();
        $reservation    = $em->getRepository('PROJETPlatformBundle:Reservation')->find($id);
        $serviceCounter = $this->get(Count::class);
        $removeTicket   = $serviceCounter->removeTicketCounter($em, $reservation);

        foreach ($reservation->getTickets() as $ticket) {
            $em->remove($ticket);
        }

        if ($removeTicket->getNumbers() <= 0) {
            $em->remove($removeTicket);
        } else {
            $em->persist($removeTicket);
        }
        
        $em->remove($reservation);
        $em->flush();

        return $this->redirectToRoute('projet_core_homepage');
    }

    public function billingAction(Request $request, $id)
    {
        $em             = $this->getDoctrine()->getManager();
        $reservation    = $em->getRepository('PROJETPlatformBundle:Reservation')->find($id);
        $serviceBilling = $this->get(Billing::class);
        $totalPrice     = $serviceBilling->calculateTotalPrice($reservation);

        if ($request->isMethod('POST')){
            $billing = $serviceBilling->billingAction($request, $totalPrice);            

            $message = \Swift_Message::newInstance()
                ->setSubject('Hello Email')
                ->setFrom('fernandes91seb@gmail.com')
                ->setTo($reservation->getEmail())
                ->setBody(
                    $this->renderView(
                        'Emails/email.html.twig',
                        array('reservation' => $reservation)
                    )
                )
                ->attach(
                    \Swift_Attachment::fromPath('https://upload.wikimedia.org/wikipedia/commons/a/a2/EAN-13-5901234123457.svg')
                        ->setDisposition('inline')
                );

            $this->get('mailer')->send($message);
            return $this->render('Emails/email.html.twig', array(
                'reservation' => $reservation
            ));
        }        

        return $this->render('PROJETPlatformBundle:Billing:bill.html.twig');
    }
}
