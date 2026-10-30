<?php

namespace App\EventSubscriber;

use App\Event\ProductEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private MailerInterface $mailer;

    /**
     * @inheritDoc
     */

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvent::PURCHASED => 'onPurchased',
        ];
    }

    public function onPurchased(ProductEvent $event){
        $this->sendMail($event);
    }

    public function sendMail(ProductEvent $event){
        $product = $event->getProduct();
        $owners = $product->getWishlist()->getWishlistOwners();

        foreach ($owners as $owner) {
            $user = $owner->getUser();

            $firstname = $user->getFirstname();
            $lastname = $user->getLastname();
            $emailAddress = $user->getEmail();

            $email = (new TemplatedEmail())
                ->from('contact@guillaume-gorvel.fr')
                ->to($emailAddress)
                ->subject('Information sur un produit de votre wishlist')
                ->htmlTemplate('emails/product_purchased.html.twig')
                ->context([
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'product' => $product,
                ]);

            $this->mailer->send($email);
        }
    }
}
