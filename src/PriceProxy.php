<?php
namespace Sellastica\Price;

use Sellastica\Localization\Presentation\CurrencyProxy;
use Sellastica\Twig\Model\ProxyObject;

/**
 * {@inheritdoc}
 * @property Price $parent
 * @method Price getParent()
 */
class PriceProxy extends ProxyObject
{
	/** @var bool */
	private $isPriceFrom = false;


	/**
	 * @return float
	 */
	public function getWith_tax(): float
	{
		return $this->parent->getWithTax();
	}

	/**
	 * @return float
	 */
	public function getWithout_tax(): float
	{
		return $this->parent->getWithoutTax();
	}

	/**
	 * @return float
	 */
	public function getTax(): float
	{
		return $this->parent->getTax();
	}

	/**
	 * @return float
	 */
	public function getTax_rate(): float
	{
		return $this->parent->getTaxRate();
	}

	/**
	 * @return float
	 */
	public function getDefault(): float
	{
		return $this->parent->getDefaultPrice();
	}

	/********************************************************************/
	/******************* Helpers (not present in twig) ******************/
	/********************************************************************/

	/**
	 * @return bool
	 */
	public function isPriceFrom(): bool
	{
		return $this->isPriceFrom;
	}

	/**
	 * @param bool $isPriceFrom
	 */
	public function setIsPriceFrom(bool $isPriceFrom): void
	{
		$this->isPriceFrom = $isPriceFrom;
	}

	/**
	 * @return CurrencyProxy
	 */
	public function getCurrency(): CurrencyProxy
	{
		return $this->parent->getCurrency()->toProxy();
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return (string)$this->__toFloat();
	}

	/**
	 * @return float
	 */
	public function __toFloat(): float
	{
		return $this->parent->getDefaultPrice();
	}

	/**
	 * @return string
	 */
	public function getShortName(): string
	{
		return 'price';
	}

	/**
	 * @return array
	 */
	public function getAllowedProperties(): array
	{
		return [
			'currency',
			'default',
			'tax',
			'tax_rate',
			'with_tax',
			'without_tax',
		];
	}
}