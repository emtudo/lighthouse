<?php

namespace Tests\Unit\Schema\AST;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Tests\TestCase;
use Tests\Utils\Models\User;

class DocumentASTTest extends TestCase
{
    public function testParsesSimpleSchema(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $documentAST->types[RootType::QUERY]
        );
    }

    public function testThrowsOnInvalidSchema(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Syntax Error: Expected Name, found !, near: ');

        DocumentAST::fromSource(/** @lang GraphQL */ '
        type Mutation {
            bar: Int
        }

        type Query {
            foo: Int!!
        }

        type Foo {
            bar: ID
        }
        ');
    }

    public function testThrowsOnUnknownModelClasses(): void
    {
        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage("Failed to find a model class for Unknown, referenced in @model on type Query");

        DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query @model(class: "Unknown") {
            foo: Int!
        }
        ');
    }

    public function testOverwritesDefinitionWithSameName(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            foo: Int
        }
        ');

        $overwrite = Parser::objectTypeDefinition(/** @lang GraphQL */ '
        type Query {
            bar: Int
        }
        ');

        $documentAST->types[$overwrite->name->value] = $overwrite;

        $this->assertSame(
            $overwrite,
            $documentAST->types[RootType::QUERY]
        );
    }

    public function testBeSerialized(): void
    {
        $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query @model(class: "User") {
            foo: Int
        }

        directive @foo on FIELD
        ');

        /** @var \Nuwave\Lighthouse\Schema\AST\DocumentAST $reserialized */
        $reserialized = unserialize(
            serialize($documentAST)
        );

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $reserialized->types[RootType::QUERY];
        $this->assertInstanceOf(
            ObjectTypeDefinitionNode::class,
            $queryType
        );

        $this->assertSame(
            'Query',
            $reserialized->classNameToObjectTypeName[User::class]
        );

        $this->assertInstanceOf(
            FieldDefinitionNode::class,
            $queryType->fields[0]
        );

        $this->assertInstanceOf(
            DirectiveDefinitionNode::class,
            $reserialized->directives['foo']
        );
    }
}
