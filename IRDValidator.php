<?php

namespace Elmo\HRCoreBundle\Validator\Constraints;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Elmo\HRCoreBundle\Entity\Superannuation;

/**
 * Class ValidIRDValidator
 * Validation for a New Zealand IRD Number (ssn equiv)
 *
 * @package Elmo\HRCoreBundle\Validator
 */
class ValidIRDValidator extends ConstraintValidator
{    
    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->entityManager = $em;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        $entity = $this->context->getObject();
        // HC-902 Keep Superannuation for legacy code
        if($entity instanceof Superannuation) {
            //only need to validate IRD if super is KiwiSaver type
            if ($entity->getType() === Superannuation::TYPE_KIWI_SAVER && !$this->checkIRD($value)) {
                $this->context->addViolation($constraint->message);
                return;
            }
        } else {
            if ($entity->getCountry() === 'NZ' && !$this->checkIRD($value)) {
                $this->context->addViolation($constraint->message);
                return;
            }
        }
                
        // Pass
    }

    /**
     * Validates a New Zealand IRD Number (ssn equiv)
     *
     * recently the format has changed to having a
     * prefix of 0, this will work with both new and old IRD numbers.
     *
     * @param string $ssn IRD number to validate
     *
     * @access  public
     * @return  bool       The valid or invalid ird number
     *
     * Part 5.1 of this document:
     * @link https://www.ird.govt.nz/resources/9/3/93296f7c-66df-434f-a97c-3a5766be14e7/payroll_payday_filing_spec_2019_%2Bv1.0.pdf
     */
    private function checkIRD($sird)
    {
        $sird = str_replace(array("-", " ", "."), "", trim($sird));
        if (!ctype_digit($sird)) {
            return false;
        }

        $ird = (int)$sird;

        // #1 - Check range
        if($ird < 10000000 || $ird > 150000000){
            return false;
        }

        // #2 - Form 8 digit base number
        if(strlen($sird) === 8){
            $trailingDigit = $sird[7];
            $baseNumbers = substr($sird, 0, 7);

            // pad to 8 digits by adding leading 0
            $baseNumbers = '0'.$baseNumbers;
        } else {
            $trailingDigit = $sird[8];
            $baseNumbers = substr($sird, 0, 8);
        }

        // #3 - Calculate check digit
        $weights = array(3, 2, 7, 6, 5, 4, 3, 2);
        $checkDigit = $this->calculateCheckDigit($weights,$baseNumbers);

        // If the calculated check digit is 10, go to step 4, else between range 0 - 9 go to step 5
        if($checkDigit === 10){
            // #4 - Re-calculate the check digit
            $weights = array(7,4,3,2,5,2,7,6);
            $checkDigit = $this->calculateCheckDigit($weights,$baseNumbers);
            return ($checkDigit === 10);
        } else if($checkDigit >= 0 && $checkDigit <= 9){
            // #5 - Company check digit with trailing digit

            return ((int)$trailingDigit === $checkDigit);
        }

        return false;
    }

    /**
     * Calculate checkDigit based logics provided by Inland Revenue Department (NZ)
     *
     * @param [] $weights List of weights for basenumbers
     * @param [] $baseNumbers
     *
     * @return int
     */
    private function calculateCheckDigit($weights, $baseNumbers)
    {
        $sum = 0;
        foreach($weights as $key=>$weight) {
            $sum += $baseNumbers[$key] * $weights[$key];
        }

        $remainder = ($sum%11);
        if($remainder === 0){
            $checkDigit = 0;
        } else {
            $checkDigit = 11 - $remainder;
        }

        return $checkDigit;
    }
}