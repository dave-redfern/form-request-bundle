<?php

namespace Adamsafr\FormRequestBundle\Tests\Resolver;

use Adamsafr\FormRequestBundle\Exception\FormValidationException;
use Adamsafr\FormRequestBundle\Locator\FormRequestServiceLocator;
use Adamsafr\FormRequestBundle\Http\FormRequest;
use Adamsafr\FormRequestBundle\Resolver\ControllerRequestResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\RecursiveValidator;

class ControllerRequestResolverTest extends TestCase
{
    public function testWithValidRequestData()
    {
        /** @var MockObject|RecursiveValidator $validator */
        $validator = $this
            ->getMockBuilder(RecursiveValidator::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $validator->expects($this->any())
            ->method('validate')
            ->willReturn(new ConstraintViolationList())
        ;

        $resolver = new ControllerRequestResolver(
            new FormRequestServiceLocator([
                TestRequest::class => function () {
                    return new TestRequest();
                },
            ]),
            $validator
        );

        $request = Request::create('/');
        $argument = new ArgumentMetadata('testRequest', TestRequest::class, false, false, null);

        $form = new TestRequest();
        $form->setRequest($request);
        $form->setJson($form->json());

        $this->assertTrue($resolver->supports($request, $argument));
        $this->assertYieldEquals([$form], $resolver->resolve($request, $argument));
    }

    public function testWithBadRequestData()
    {
        /** @var MockObject|ConstraintViolationList $constraints */
        $constraints = $this
            ->getMockBuilder(ConstraintViolationList::class)
            ->getMock()
        ;
        $constraints->expects($this->any())
            ->method('count')
            ->willReturn(1)
        ;

        /** @var MockObject|RecursiveValidator $validator */
        $validator = $this
            ->getMockBuilder(RecursiveValidator::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $validator->expects($this->any())
            ->method('validate')
            ->willReturn($constraints)
        ;

        $resolver = new ControllerRequestResolver(
            new FormRequestServiceLocator([
                TestRequest::class => function () {
                    return new TestRequest();
                },
            ]),
            $validator
        );

        $request = Request::create('/');
        $argument = new ArgumentMetadata('testRequest', TestRequest::class, false, false, null);

        $form = new TestRequest();

        $this->assertTrue($resolver->supports($request, $argument));
        $this->expectException(FormValidationException::class);
        $this->assertYieldEquals([$form], $resolver->resolve($request, $argument));
    }

    public function testNotAuthorizedRequest()
    {
        /** @var MockObject|RecursiveValidator $validator */
        $validator = $this
            ->getMockBuilder(RecursiveValidator::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $resolver = new ControllerRequestResolver(
            new FormRequestServiceLocator([
                TestRequest::class => function () {
                    return $this->getNotAuthorizedTestRequestMock();
                },
            ]),
            $validator
        );

        $request = Request::create('/');
        $argument = new ArgumentMetadata('testRequest', TestRequest::class, false, false, null);

        $this->assertTrue($resolver->supports($request, $argument));
        $this->expectException(AccessDeniedHttpException::class);
        $this->assertYieldEquals([$this->getNotAuthorizedTestRequestMock()], $resolver->resolve($request, $argument));
    }

    /**
     * @return MockObject|TestRequest
     */
    private function getNotAuthorizedTestRequestMock()
    {
        $testRequest = $this->getMockBuilder(TestRequest::class)->getMock();

        $testRequest->expects($this->any())
            ->method('authorize')
            ->willReturn(false);

        return $testRequest;
    }

    private function assertYieldEquals(array $expected, \Generator $generator)
    {
        $args = [];
        foreach ($generator as $arg) {
            $args[] = $arg;
        }

        $this->assertEquals($expected, $args);
    }
}

class TestRequest extends FormRequest
{
}
