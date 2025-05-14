<?php

declare(strict_types=1);
/**
 * Copyright (c) The Magic , Distributed under the software license
 */

namespace App\Domain\Flow\Entity\ValueObject\NodeParamsConfig\Search\V1;

use App\Domain\Flow\Entity\ValueObject\NodeOutput;
use App\Domain\Flow\Entity\ValueObject\NodeParamsConfig\Search\AbstractSearchNodeParamsConfig;
use Dtyq\FlowExprEngine\ComponentFactory;
use Dtyq\FlowExprEngine\Structure\StructureType;
use Hyperf\Codec\Json;

class UserSearchNodeParamsConfig extends AbstractSearchNodeParamsConfig
{
    public function generateTemplate(): void
    {
        parent::generateTemplate();
        $output = new NodeOutput();
        $output->setForm(ComponentFactory::generateTemplate(StructureType::Form, Json::decode(<<<'JSON'
{
    "type": "object",
    "key": "root",
    "sort": 0,
    "title": "root节点",
    "description": "",
    "items": null,
    "value": null,
    "required": [],
    "properties": {
        "users": {
            "type": "array",
            "key": "users",
            "sort": 0,
            "title": "用户数据",
            "description": "desc",
            "items": {
                "type": "object",
                "key": "users",
                "sort": 0,
                "title": "用户数据",
                "description": "desc",
                "required": [
                    "user_id",
                    "real_name",
                    "position",
                    "phone_number",
                    "work_number"
                ],
                "encryption": false,
                "encryption_value": null,
                "items": null,
                "value": null,
                "properties": {
                    "user_id": {
                        "type": "string",
                        "key": "user_id",
                        "sort": 0,
                        "title": "ID",
                        "description": "",
                        "items": null,
                        "properties": null,
                        "required": null,
                        "encryption": false,
                        "encryption_value": null,
                        "value": null
                    },
                    "real_name": {
                        "type": "string",
                        "key": "real_name",
                        "sort": 1,
                        "title": "姓名",
                        "description": "",
                        "items": null,
                        "properties": null,
                        "required": null,
                        "encryption": false,
                        "encryption_value": null,
                        "value": null
                    },
                    "position": {
                        "type": "string",
                        "key": "position",
                        "sort": 2,
                        "title": "岗位",
                        "description": "",
                        "items": null,
                        "properties": null,
                        "required": null,
                        "encryption": false,
                        "encryption_value": null,
                        "value": null
                    },
                    "phone_number": {
                        "type": "string",
                        "key": "phone_number",
                        "sort": 3,
                        "title": "手机号码",
                        "description": "",
                        "items": null,
                        "properties": null,
                        "required": null,
                        "encryption": false,
                        "encryption_value": null,
                        "value": null
                    },
                    "work_number": {
                        "type": "string",
                        "key": "work_number",
                        "sort": 4,
                        "title": "工号",
                        "description": "",
                        "items": null,
                        "properties": null,
                        "required": null,
                        "encryption": false,
                        "encryption_value": null,
                        "value": null
                    },
                    "departments": {
                        "type": "array",
                        "key": "departments",
                        "sort": 5,
                        "title": "部门",
                        "description": "desc",
                        "required": [],
                        "encryption": false,
                        "encryption_value": null,
                        "items": {
                            "type": "object",
                            "key": "departments",
                            "sort": 0,
                            "title": "部门",
                            "description": "desc",
                            "required": [
                                "id",
                                "name",
                                "path"
                            ],
                            "encryption": false,
                            "encryption_value": null,
                            "items": null,
                            "properties": {
                                "id": {
                                    "type": "string",
                                    "title": "部门 ID",
                                    "description": "",
                                    "key": "id",
                                    "sort": 0,
                                    "items": null,
                                    "properties": null,
                                    "required": null,
                                    "encryption": false,
                                    "encryption_value": null,
                                    "value": null
                                },
                                "name": {
                                    "type": "string",
                                    "title": "部门名称",
                                    "description": "",
                                    "key": "name",
                                    "sort": 1,
                                    "items": null,
                                    "properties": null,
                                    "required": null,
                                    "encryption": false,
                                    "encryption_value": null,
                                    "value": null
                                },
                                "path": {
                                    "type": "string",
                                    "title": "部门路径",
                                    "description": "",
                                    "key": "path",
                                    "sort": 2,
                                    "items": null,
                                    "properties": null,
                                    "required": null,
                                    "encryption": false,
                                    "encryption_value": null,
                                    "value": null
                                }
                            },
                            "value": null
                        },
                        "properties": null,
                        "value": null
                    }
                }
            },
            "properties": null,
            "value": null,
            "encryption": false,
            "encryption_value": null,
            "required": null
        }
    }
}
JSON)));
        $this->node->setOutput($output);
    }
}
