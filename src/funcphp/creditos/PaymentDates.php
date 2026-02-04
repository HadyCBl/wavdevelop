<?php
namespace Creditos\Utilidades;
use \DateTime;
use \InvalidArgumentException;

class PaymentDates {
    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly'; 
    const PERIOD_BIWEEKLY = 'biweekly';
    const PERIOD_FORTNIGHTLY = 'fortnightly';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_BIMONTHLY = 'bimonthly';
    const PERIOD_QUARTERLY = 'quarterly';
    const PERIOD_SEMIANNUAL = 'semiannual';
    const PERIOD_ANNUAL = 'annual';

    private $startDate;
    private $periodType;
    private $totalPayments;

    public function __construct(DateTime $startDate, string $periodType, int $totalPayments) {
        $this->startDate = $startDate;
        $this->periodType = $periodType;
        $this->totalPayments = $totalPayments;
    }

    public function calculatePaymentDates(): array {
        $dates = [];
        $currentDate = clone $this->startDate;

        for ($i = 0; $i < $this->totalPayments; $i++) {
            $dates[] = clone $currentDate;
            
            switch ($this->periodType) {
                case self::PERIOD_DAILY:
                    $currentDate->modify('+1 day');
                    break;
                case self::PERIOD_WEEKLY:
                    $currentDate->modify('+1 week');
                    break;
                case self::PERIOD_BIWEEKLY:
                    $currentDate->modify('+2 weeks');
                    break;
                case self::PERIOD_FORTNIGHTLY:
                    $currentDate->modify('+14 days');
                    break;
                case self::PERIOD_MONTHLY:
                    $currentDate->modify('+1 month');
                    break;
                case self::PERIOD_BIMONTHLY:
                    $currentDate->modify('+2 months');
                    break;
                case self::PERIOD_QUARTERLY:
                    $currentDate->modify('+3 months');
                    break;
                case self::PERIOD_SEMIANNUAL:
                    $currentDate->modify('+6 months');
                    break;
                case self::PERIOD_ANNUAL:
                    $currentDate->modify('+1 year');
                    break;
                default:
                    throw new InvalidArgumentException('Invalid period type');
            }
        }

        return $dates;
    }

    public function getNextPaymentDate(DateTime $referenceDate): ?DateTime {
        $paymentDates = $this->calculatePaymentDates();
        
        foreach ($paymentDates as $date) {
            if ($date > $referenceDate) {
                return $date;
            }
        }
        
        return null;
    }

    public function getRemainingPayments(DateTime $referenceDate): int {
        $paymentDates = $this->calculatePaymentDates();
        $remaining = 0;
        
        foreach ($paymentDates as $date) {
            if ($date > $referenceDate) {
                $remaining++;
            }
        }
        
        return $remaining;
    }

    public function getPaymentNumber(DateTime $referenceDate): ?int {
        $paymentDates = $this->calculatePaymentDates();
        
        foreach ($paymentDates as $index => $date) {
            if ($date->format('Y-m-d') === $referenceDate->format('Y-m-d')) {
                return $index + 1;
            }
        }
        
        return null;
    }
}
