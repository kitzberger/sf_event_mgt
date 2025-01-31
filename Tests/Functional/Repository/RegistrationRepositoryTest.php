<?php

declare(strict_types=1);

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace DERHANSEN\SfEventMgt\Tests\Functional\Repository;

use DERHANSEN\SfEventMgt\Domain\Model\Dto\CustomNotification;
use DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand;
use DERHANSEN\SfEventMgt\Domain\Model\Event;
use DERHANSEN\SfEventMgt\Domain\Repository\FrontendUserRepository;
use DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case for class \DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository
 */
class RegistrationRepositoryTest extends FunctionalTestCase
{
    /**
     * @var \DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository
     */
    protected $registrationRepository;

    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/sf_event_mgt'];

    /**
     * Setup
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->registrationRepository = GeneralUtility::makeInstance(RegistrationRepository::class);

        $this->importDataSet(__DIR__ . '/../Fixtures/tx_sfeventmgt_domain_model_registration.xml');
    }

    /**
     * Test for match on Event
     *
     * @test
     */
    public function findNotificationRegistrationsForEventUid2()
    {
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event->expects(self::once())->method('getUid')->willReturn(2);
        $customNotification = $this->getMockBuilder(CustomNotification::class)->getMock();
        $registrations = $this->registrationRepository->findNotificationRegistrations($event, $customNotification, []);
        self::assertEquals(1, $registrations->count());
    }

    public function confirmedAndUnconfirmedDataProvider()
    {
        return [
            'all registrations' => [
                0,
                3,
            ],
            'confirmed' => [
                1,
                2,
            ],
            'unconfirmed' => [
                2,
                1,
            ],
        ];
    }

    /**
     * @dataProvider confirmedAndUnconfirmedDataProvider
     * @test
     */
    public function findNotificationRegistrationsReturnsConfirmedAndUnconfirmed($recipientSetting, $expected)
    {
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event->expects(self::once())->method('getUid')->willReturn(20);
        $customNotification = $this->getMockBuilder(CustomNotification::class)->getMock();
        $customNotification->expects(self::any())->method('getRecipients')->willReturn($recipientSetting);
        $registrations = $this->registrationRepository->findNotificationRegistrations($event, $customNotification, []);
        self::assertSame($expected, $registrations->count());
    }

    /**
     * Data provider for findExpiredRegistrations
     *
     * @return array
     */
    public function findNotificationRegistrationsDataProvider()
    {
        return [
            'withEmptyConstraints' => [
                [],
                3,
            ],
            'allPaidEquals1' => [
                [
                    'paid' => ['equals' => '1'],
                ],
                2,
            ],
            'confirmationUntilLessThan' => [
                [
                    'confirmationUntil' => ['lessThan' => '1402743600'],
                ],
                2,
            ],
            'confirmationUntilLessThanOrEqual' => [
                [
                    'confirmationUntil' => ['lessThanOrEqual' => '1402743600'],
                ],
                3,
            ],
            'confirmationUntilGreaterThan' => [
                [
                    'confirmationUntil' => ['greaterThan' => '1402740000'],
                ],
                1,
            ],
            'confirmationUntilGreaterThanOrEqual' => [
                [
                    'confirmationUntil' => ['greaterThanOrEqual' => '1402740000'],
                ],
                3,
            ],
            'multipleContraints' => [
                [
                    'confirmationUntil' => ['lessThan' => '1402743600'],
                    'paid' => ['equals' => '0'],
                ],
                1,
            ],
        ];
    }

    /**
     * Test for match on Event
     *
     * @dataProvider findNotificationRegistrationsDataProvider
     * @test
     * @param mixed $constraints
     * @param mixed $expected
     */
    public function findNotificationRegistrationsForEventUid1WithConstraints($constraints, $expected)
    {
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event->expects(self::once())->method('getUid')->willReturn(1);

        $customNotification = $this->getMockBuilder(CustomNotification::class)->getMock();

        $registrations = $this->registrationRepository->findNotificationRegistrations(
            $event,
            $customNotification,
            $constraints
        );
        self::assertEquals($expected, $registrations->count());
    }

    /**
     * Test for match on Event with unknown condition
     *
     * @test
     */
    public function findNotificationRegistrationsForEventWithConstraintsButWrongCondition()
    {
        $this->expectException(InvalidArgumentException::class);
        $constraints = ['confirmationUntil' => ['wrongcondition' => '0']];
        $event = $this->getMockBuilder(Event::class)->getMock();
        $customNotification = $this->getMockBuilder(CustomNotification::class)->getMock();
        $this->registrationRepository->findNotificationRegistrations($event, $customNotification, $constraints);
    }

    /**
     * Test if ignoreNotifications is respected
     *
     * @test
     */
    public function findNotificationRegistrationsRespectsIgnoreNotificationsForEventUid3()
    {
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event->expects(self::once())->method('getUid')->willReturn(3);
        $customNotification = $this->getMockBuilder(CustomNotification::class)->getMock();
        $registrations = $this->registrationRepository->findNotificationRegistrations(
            $event,
            $customNotification,
            []
        );
        self::assertEquals(1, $registrations->count());
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand returns an empty array if no user given
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandReturnsEmptyArrayIfNoUser()
    {
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('all');
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals([], $registrations);
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand respects storagePage constraint
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandRespectsStoragePage()
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository $feUserRepository */
        $feUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        $feUser = $feUserRepository->findByUid(1);
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('all');
        $demand->setStoragePage('7');
        $demand->setUser($feUser);
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals(3, $registrations->count());
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand respects displayMode constraint
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandRespectsDisplaymode()
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository $feUserRepository */
        $feUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        $feUser = $feUserRepository->findByUid(1);
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('future');
        $demand->setStoragePage('7');
        $demand->setUser($feUser);
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals(0, $registrations->count());
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand respects user constraint
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandRespectsUser()
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository $feUserRepository */
        $feUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        $feUser = $feUserRepository->findByUid(2);
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('all');
        $demand->setStoragePage('7');
        $demand->setUser($feUser);
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals(1, $registrations->count());
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand respects order constraint
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandRespectsOrder()
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository $feUserRepository */
        $feUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        $feUser = $feUserRepository->findByUid(1);
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('all');
        $demand->setStoragePage('7');
        $demand->setUser($feUser);
        $demand->setOrderField('event.startdate');
        $demand->setOrderDirection('asc');
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals(30, $registrations->getFirst()->getUid());
    }

    /**
     * Test if findRegistrationsByUserRegistrationDemand respects order direction constraint
     *
     * @test
     */
    public function findRegistrationsByUserRegistrationDemandRespectsOrderDirection()
    {
        /** @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository $feUserRepository */
        $feUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
        $feUser = $feUserRepository->findByUid(1);
        /** @var \DERHANSEN\SfEventMgt\Domain\Model\Dto\UserRegistrationDemand $demand */
        $demand = new UserRegistrationDemand();
        $demand->setDisplayMode('all');
        $demand->setStoragePage('7');
        $demand->setUser($feUser);
        $demand->setOrderField('event.startdate');
        $demand->setOrderDirection('desc');
        $registrations = $this->registrationRepository->findRegistrationsByUserRegistrationDemand($demand);
        self::assertEquals(32, $registrations->getFirst()->getUid());
    }

    /**
     * @test
     */
    public function findWaitlistMoveUpRegistrationsReturnsExpectedAmountOfRegistrationsAndRespectsOrder()
    {
        $event = $this->getMockBuilder(Event::class)->getMock();
        $event->expects(self::once())->method('getUid')->willReturn(30);

        $registrations = $this->registrationRepository->findWaitlistMoveUpRegistrations($event);
        self::assertSame(2, $registrations->count());

        // Event with UID 50 should be first, since registration_date is ealier than registration UID 51
        self::assertSame(50, $registrations->getFirst()->getUid());
    }
}
