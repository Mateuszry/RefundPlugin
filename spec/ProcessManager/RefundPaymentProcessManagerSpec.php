<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\RefundPlugin\ProcessManager;

use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Sylius\RefundPlugin\Event\UnitsRefunded;
use Sylius\RefundPlugin\Factory\RefundPaymentFactoryInterface;
use Sylius\RefundPlugin\Model\OrderItemUnitRefund;
use Sylius\RefundPlugin\Model\ShipmentRefund;
use Sylius\RefundPlugin\ProcessManager\UnitsRefundedProcessStepInterface;
use Sylius\RefundPlugin\Provider\RelatedPaymentIdProviderInterface;
use Sylius\RefundPlugin\StateResolver\OrderFullyRefundedStateResolverInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RefundPaymentProcessManagerSpec extends ObjectBehavior
{
    public function let(
        OrderFullyRefundedStateResolverInterface $orderFullyRefundedStateResolver,
        RelatedPaymentIdProviderInterface $relatedPaymentIdProvider,
        RefundPaymentFactoryInterface $refundPaymentFactory,
        EntityManagerInterface $entityManager,
        MessageBusInterface $eventBus
    ): void {
        $this->beConstructedWith(
            $orderFullyRefundedStateResolver,
            $relatedPaymentIdProvider,
            $refundPaymentFactory,
            $entityManager,
            $eventBus
        );
    }

    public function it_implements_units_refunded_process_step_interface(): void
    {
        $this->shouldImplement(UnitsRefundedProcessStepInterface::class);
    }

    public function it_reacts_on_units_refunded_event_and_creates_refund_payment(
        OrderFullyRefundedStateResolverInterface $orderFullyRefundedStateResolver,
        RelatedPaymentIdProviderInterface $relatedPaymentIdProvider,
        RefundPaymentFactoryInterface $refundPaymentFactory,
        EntityManagerInterface $entityManager,
        RefundPaymentInterface $refundPayment,
        MessageBusInterface $eventBus
    ): void {
        $refundPaymentFactory->createWithData(
            '000222',
            1000,
            'USD',
            RefundPaymentInterface::STATE_NEW,
            1
        )->willReturn($refundPayment);

        $entityManager->persist($refundPayment)->shouldBeCalled();
        $entityManager->flush()->shouldBeCalled();

        $orderFullyRefundedStateResolver->resolve('000222')->shouldBeCalled();

        $refundPayment->getId()->willReturn(10);
        $refundPayment->getOrderNumber()->willReturn('000222');
        $refundPayment->getAmount()->willReturn(1000);

        $relatedPaymentIdProvider->getForRefundPayment($refundPayment)->willReturn(3);

        $event = new RefundPaymentGenerated(10, '000222', 1000, 'USD', 1, 3);
        $eventBus->dispatch($event)->willReturn(new Envelope($event))->shouldBeCalled();

        $this->next(new UnitsRefunded(
            '000222',
            [new OrderItemUnitRefund(1, 500), new OrderItemUnitRefund(2, 500)],
            [new ShipmentRefund(1, 300)],
            1,
            1000,
            'USD',
            'Comment'
        ));
    }
}
