<?php
namespace Sellastica\Price;

use Sellastica\Localization\Model\Currency;
use Sellastica\Twig\Model\IProxable;
use Sellastica\Price\PriceProxy;
use Sellastica\Utils\Strings;
use Sellastica\Twig\Model\ProxyConverter;

class Price implements IProxable
{
	const VAT_COEF_PRECISION = 4;

	/** @var float */
	private $withoutTax;
	/** @var float */
	private $tax;
	/** @var float */
	private $withTax;
	/** @var float */
	private $taxRate;
	/** @var Currency */
	private $currency;

	/** @var float */
	private $defaultPrice;
	/** @var bool */
	private $defaultPriceIncludesTax;


	/**
	 * @param Currency $currency
	 * @param float $price It can be NULL in case of total prices (tax rate levels are mixed)
	 * @param bool $includesTax
	 * @param float $taxRate
	 */
	public function __construct(float $price, bool $includesTax, float $taxRate, Currency $currency)
	{
		$this->assertTaxRate($taxRate);

		$this->defaultPriceIncludesTax = $includesTax;
		$this->taxRate = $taxRate;
		$this->currency = $currency;
		$this->defaultPrice = $this->round($price);

		if (true === $this->defaultPriceIncludesTax) {
			$this->withTax = $this->defaultPrice;
			$this->tax = $this->round($this->defaultPrice * $this->getTaxCoef());
			$this->withoutTax = $this->withTax - $this->tax;
		} else {
			$this->withoutTax = $this->defaultPrice;
			$this->tax = $this->round($this->withoutTax * $this->taxRate / 100);
			$this->withTax = $this->withoutTax + $this->tax;
		}
	}

	/**
	 * @return float
	 */
	public function getWithoutTax(): float
	{
		return $this->withoutTax;
	}

	/**
	 * @return float
	 */
	public function getTax(): float
	{
		return $this->tax;
	}

	/**
	 * @return float
	 */
	public function getWithTax(): float
	{
		return $this->withTax;
	}

	/**
	 * @param bool $withTax
	 * @return float
	 */
	public function getWithOrWithoutTax(bool $withTax): float
	{
		return $withTax ? $this->withTax : $this->withoutTax;
	}

	/**
	 * @return float|null If price is combined from prices with different tax rates, resulting tax rate is null
	 */
	public function getTaxRate(): ?float
	{
		return $this->taxRate;
	}

	/**
	 * @return Currency
	 */
	public function getCurrency(): Currency
	{
		return $this->currency;
	}

	/**
	 * @return float
	 */
	public function getDefaultPrice(): float
	{
		return $this->defaultPrice;
	}

	/**
	 * @return boolean
	 */
	public function defaultPriceIncludesTax(): bool
	{
		return $this->defaultPriceIncludesTax;
	}

	/**
	 * @param bool $defaultPriceIncludesTax
	 */
	public function setDefaultPriceIncludesTax(bool $defaultPriceIncludesTax): void
	{
		$this->defaultPrice = $defaultPriceIncludesTax ? $this->withTax : $this->withoutTax;
		$this->defaultPriceIncludesTax = $defaultPriceIncludesTax;
	}

	/**
	 * @return Price
	 */
	public function clone(): Price
	{
		return clone $this;
	}

	/**
	 * @param float $coef
	 * @return Price
	 */
	public function multiply(float $coef): Price
	{
		if ($coef === 1) {
			return clone $this;
		}

		$newPrice = clone $this;
		$newPrice->defaultPrice = $this->round($newPrice->defaultPrice * $coef);
		$newPrice->withoutTax = $this->round($newPrice->withoutTax * $coef);
		$newPrice->withTax = $this->round($newPrice->withTax * $coef);
		$newPrice->tax = $this->round($newPrice->tax * $coef);

		return $newPrice;
	}

	/**
	 * @param float $coef
	 * @return Price
	 */
	public function divide(float $coef): Price
	{
		$this->assertDivisionCoef($coef);
		return $this->multiply(1 / $coef);
	}

	/**
	 * @param Price $price
	 * @return Price
	 */
	public function add(Price $price): Price
	{
		$this->assertSameCurrency($price, $this);
		$this->assertPrice($price, $this);

		$newPrice = $this->fromPrice($price);
		$newPrice->defaultPrice += Strings::floatify($price->getDefaultPrice());
		$newPrice->withoutTax += Strings::floatify($price->getWithoutTax());
		$newPrice->withTax += Strings::floatify($price->getWithTax());
		$newPrice->tax += Strings::floatify($price->getTax());

		return $newPrice;
	}

	/**
	 * @param Price $price
	 * @return Price
	 */
	public function subtract(Price $price): Price
	{
		$this->assertSameCurrency($price, $this);
		$this->assertPrice($price, $this);
		$newPrice = $this->fromPrice($price);
		$newPrice->defaultPrice -= Strings::floatify($price->getDefaultPrice());
		$newPrice->withoutTax -= Strings::floatify($price->getWithoutTax());
		$newPrice->withTax -= Strings::floatify($price->getWithTax());
		$newPrice->tax -= Strings::floatify($price->getTax());

		//php incorrect counting preventing
		$newPrice->defaultPrice = $this->round($newPrice->defaultPrice);
		$newPrice->withoutTax = $this->round($newPrice->withoutTax);
		$newPrice->withTax = $this->round($newPrice->withTax);
		$newPrice->tax = $this->round($newPrice->tax);

		return $newPrice;
	}

	/**
	 * @param float $amount
	 * @param string $type
	 * @return Price
	 */
	public function discount(float $amount, string $type): Price
	{
		$this->assertDiscountType($type);
		$this->assertDiscountAmount($amount);

		if ($type === \Core\Domain\Model\Price\Discount::PERCENTUAL) {
			return $this->multiply(1 - $amount / 100);
		}

		//summary price can have null as tax rate
		$taxRate = $this->taxRate ?? $this->tax / $this->withTax * 100;
		$this->assertTaxRate($taxRate);
		return $this->subtract(new self(
			$amount, $this->defaultPriceIncludesTax, $taxRate, $this->currency
		));
	}

	/**
	 * @return Price
	 */
	public function zeroize(): Price
	{
		return new self(0, $this->defaultPriceIncludesTax, $this->taxRate ?? 0, $this->currency);
	}

	/**
	 * @param float $price
	 * @param float $taxRate
	 * @return Price
	 */
	public function modify(float $price, float $taxRate = null): Price
	{
		$taxRate = $taxRate ?? $this->taxRate;
		$this->assertTaxRate($taxRate);
		return new self(
			$price,
			$this->defaultPriceIncludesTax,
			$taxRate,
			$this->currency
		);
	}

	/**
	 * We cannot use multiply() method for this, because calculations may differ!
	 * @return Price
	 */
	public function toSubUnits(): Price
	{
		$subUnit = $this->currency->getSubUnit();
		$price = clone $this;
		$price->withoutTax = $price->withoutTax * $subUnit;
		$price->withTax = $price->withTax * $subUnit;
		$price->tax = $price->tax * $subUnit;
		$price->defaultPrice = $price->defaultPrice * $subUnit;

		return $price;
	}

	/**
	 * @param \Sellastica\Localization\Model\Currency $currency
	 * @param float $exchangeRate
	 * @return Price
	 */
	public function convertTo(Currency $currency, float $exchangeRate): Price
	{
		$this->assertConversionParams($currency, $exchangeRate);
		if ($currency->equals($this->currency)) {
			return clone $this;
		} else {
			$this->assertTaxRate($this->taxRate);
			return new self(
				$this->defaultPrice / $exchangeRate,
				$this->defaultPriceIncludesTax,
				$this->taxRate,
				$currency
			);
		}
	}

	/**
	 * @param Price $price
	 * @return bool
	 */
	public function equals(Price $price)
	{
		return $this->withoutTax == $price->getWithoutTax()
			&& $this->taxRate == $price->getTaxRate()
			&& $this->withTax == $price->getWithTax()
			&& $this->currency->equals($price->getCurrency());
	}

	/**
	 * @param Price $price
	 * @return bool
	 */
	public function isHigherThan(Price $price): bool
	{
		$this->assertSameCurrency($price, $this);
		return $this->defaultPrice > $price->getDefaultPrice();
	}

	/**
	 * @return bool
	 */
	public function isZero(): bool
	{
		return $this->defaultPrice == 0
			&& $this->withoutTax == 0
			&& $this->withTax == 0
			&& $this->tax == 0;
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return (string)$this->defaultPrice;
	}

	/**
	 * @param Price $price
	 * @return Price
	 */
	private function fromPrice(Price $price): Price
	{
		$newPrice = clone $this;
		if ($newPrice->isZero()) {
			$newPrice->taxRate = $price->getTaxRate();
			$newPrice->defaultPriceIncludesTax = $price->defaultPriceIncludesTax();
		} elseif (!$price->isZero() && $newPrice->taxRate !== $price->getTaxRate()) {
			$newPrice->taxRate = null;
		}

		return $newPrice;
	}

	/**
	 * @param float $taxRate
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 */
	private function assertTaxRate($taxRate): void
	{
		if ($taxRate < 0) {
			throw new \InvalidArgumentException(sprintf('Tax rate must be grater than zero, %s given', $taxRate));
		} elseif ($taxRate === null) {
			throw new \LogicException('Tax rate cannot be null');
		}
	}

	/**
	 * @param Price $a
	 * @param Price $b
	 * @throws \LogicException
	 */
	private function assertPrice(Price $a, Price $b): void
	{
		if (!$a->isZero() && !$b->isZero() && $a->defaultPriceIncludesTax() !== $b->defaultPriceIncludesTax()) {
			throw new \LogicException('Cannot combine default price with and without tax');
		}
	}

	/**
	 * @param Price $a
	 * @param Price $b
	 * @throws \LogicException
	 */
	private function assertSameCurrency(Price $a, Price $b): void
	{
		if (!$a->getCurrency()->equals($b->getCurrency())) {
			throw new \LogicException('Cannot combine prices with different currencies');
		}
	}

	/**
	 * @param string $type
	 * @throws \InvalidArgumentException
	 */
	private function assertDiscountType(string $type): void
	{
		if ($type !== \Core\Domain\Model\Price\Discount::NOMINAL && $type !== \Core\Domain\Model\Price\Discount::PERCENTUAL) {
			throw new \InvalidArgumentException(sprintf('Invalid discount type "%s"', $type));
		}
	}

	/**
	 * @param float $amount
	 * @throws \InvalidArgumentException
	 */
	private function assertDiscountAmount(float $amount): void
	{
		if ($amount < 0) {
			throw new \InvalidArgumentException(sprintf('Invalid discount amount "%s"', $amount));
		}
	}

	/**
	 * @param float $coef
	 * @throws \InvalidArgumentException
	 */
	private function assertDivisionCoef(float $coef): void
	{
		if ($coef <= 0) {
			throw new \InvalidArgumentException(sprintf(
				'Division coefficient must be grater than zero, %s given', $coef
			));
		}
	}

	/**
	 * @param \Sellastica\Localization\Model\Currency $currency
	 * @param float $exchangeRate
	 * @throws \Exception
	 */
	private function assertConversionParams(Currency $currency, float $exchangeRate): void
	{
		if ($currency->equals($this->currency) && $exchangeRate !== 1) {
			throw new \Exception(sprintf(
				'Conversion request mismatch. Currency cannot be the same while conversion rate is %s', $exchangeRate
			));
		}
	}

	/**
	 * @param float $price
	 * @return float
	 */
	private function round(float $price): float
	{
		$price = $this->currency->round($price);
		//change minus zero to zero
		return $price != 0 ? $price : 0; //!=
	}

	/**
	 * @return float
	 */
	private function getTaxCoef(): float
	{
		return round($this->taxRate / (100 + $this->taxRate), self::VAT_COEF_PRECISION);
	}

	/**
	 * @return PriceProxy
	 */
	public function toProxy(): PriceProxy
	{
		return ProxyConverter::convert($this, PriceProxy::class);
	}

	/**
	 * @param \Sellastica\Localization\Model\Currency $currency
	 * @param bool $includesTax
	 * @return Price
	 */
	public static function zero(Currency $currency, bool $includesTax = false): Price
	{
		return new self(0, $includesTax, 0, $currency);
	}

	/**
	 * Creates price with taxRate = null and without any calculations
	 * @param float $priceWitTax
	 * @param float $tax
	 * @param Currency $currency
	 * @param bool $defaultPriceIncludesTax
	 * @return Price
	 */
	public static function sumPrice(
		float $priceWitTax,
		float $tax,
		Currency $currency,
		bool $defaultPriceIncludesTax = false
	): Price
	{
		$price = self::zero($currency);
		$price->withTax = $priceWitTax;
		$price->withoutTax = $priceWitTax - $tax;
		$price->tax = $tax;
		$price->taxRate = null;
		$price->defaultPrice = $defaultPriceIncludesTax ? $price->withTax : $price->withoutTax;

		return $price;
	}
}