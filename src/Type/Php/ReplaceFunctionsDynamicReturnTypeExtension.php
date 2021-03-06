<?php declare(strict_types = 1);

namespace PHPStan\Type\Php;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class ReplaceFunctionsDynamicReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{

	/** @var array<string, int> */
	private $functions = [
		'preg_replace' => 2,
		'preg_replace_callback' => 2,
		'preg_replace_callback_array' => 1,
		'str_replace' => 2,
	];

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return array_key_exists($functionReflection->getName(), $this->functions);
	}

	public function getTypeFromFunctionCall(
		FunctionReflection $functionReflection,
		FuncCall $functionCall,
		Scope $scope
	): Type
	{
		$argumentPosition = $this->functions[$functionReflection->getName()];
		if (count($functionCall->args) <= $argumentPosition) {
			return ParametersAcceptorSelector::selectSingle($functionReflection->getVariants())->getReturnType();
		}

		$subjectArgumentType = $scope->getType($functionCall->args[$argumentPosition]->value);
		$stringType = new StringType();
		$arrayType = new ArrayType(new MixedType(), new MixedType());

		$isStringSuperType = $stringType->isSuperTypeOf($subjectArgumentType);
		$isArraySuperType = $arrayType->isSuperTypeOf($subjectArgumentType);
		$compareSuperTypes = $isStringSuperType->compareTo($isArraySuperType);
		if ($compareSuperTypes === $isStringSuperType) {
			return $stringType;
		} elseif ($compareSuperTypes === $isArraySuperType) {
			if ($subjectArgumentType instanceof ArrayType) {
				return $subjectArgumentType->generalizeValues();
			}
			return $subjectArgumentType;
		}

		return ParametersAcceptorSelector::selectSingle($functionReflection->getVariants())->getReturnType();
	}

}
