<?php

namespace App\Command;

use App\Repository\ProductUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:reservation-reminders',
    description: 'Envoie un rappel aux utilisateurs ayant réservé un cadeau.',
)]
class SendReservationRemindersCommand extends Command
{
    public function __construct(
        private readonly ProductUserRepository $productUserRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Affiche les rappels qui seraient envoyés sans envoyer de mail.'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle(
            $input,
            $output
        );

        $dryRun = (bool) $input->getOption(
            'dry-run'
        );

        $now = new \DateTimeImmutable();

        $reservations = $this
            ->productUserRepository
            ->findReservationsToRemind($now);

        if (count($reservations) === 0) {

            $io->success(
                'Aucune réservation à rappeler.'
            );

            return Command::SUCCESS;
        }

        $sentCount = 0;

        foreach ($reservations as $reservation) {

            $user = $reservation->getUser();
            $product = $reservation->getProduct();
            $wishlist = $product->getWishlist();

            if (
                $user === null
                || $product === null
                || $wishlist === null
            ) {
                continue;
            }

            $io->writeln(
                sprintf(
                    '%s - %s - rappel prévu le %s',
                    $user->getEmail(),
                    $product->getName(),
                    $reservation
                        ->getReservationReminderAt()
                        ->format('d/m/Y H:i')
                )
            );

            if ($dryRun) {
                continue;
            }

            $wishlistUrl = $this
                ->urlGenerator
                ->generate(
                    'app_wishlist',
                    [
                        'token' => $wishlist->getAccessToken(),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

            $email = (new TemplatedEmail())
                ->from(
                    new Address(
                        'contact@guillaume-gorvel.fr',
                        'Nidou'
                    )
                )
                ->to(
                    $user->getEmail()
                )
                ->subject(
                    'Petit rappel concernant votre cadeau réservé 🎁'
                )
                ->htmlTemplate(
                    'emails/reservation_reminder.html.twig'
                )
                ->context([
                    'user' => $user,
                    'product' => $product,
                    'wishlist' => $wishlist,
                    'wishlistUrl' => $wishlistUrl,
                ]);

            try {

                $this->mailer->send(
                    $email
                );

                $reservation->setIsReminded(
                    true
                );

                $this->entityManager->flush();

                $sentCount++;

            } catch (\Throwable $e) {

                $io->error(
                    sprintf(
                        'Impossible d’envoyer le rappel à %s : %s',
                        $user->getEmail(),
                        $e->getMessage()
                    )
                );
            }
        }

        if ($dryRun) {

            $io->success(
                sprintf(
                    '%d rappel(s) seraient envoyés.',
                    count($reservations)
                )
            );

            return Command::SUCCESS;
        }

        $io->success(
            sprintf(
                '%d rappel(s) envoyé(s).',
                $sentCount
            )
        );

        return Command::SUCCESS;
    }
}
