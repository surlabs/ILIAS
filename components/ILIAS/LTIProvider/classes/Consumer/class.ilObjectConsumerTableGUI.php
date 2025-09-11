<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

declare(strict_types=1);

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\Column\Column;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use ILIAS\HTTP\Services;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\Data\Range;
use ILIAS\Data\Order;

/**
 * Table for listing LTI consumers using the new UI framework
 *
 * @author Jesús López <lopez@leifos.com>
 * @author Felix Wensing <felix.wensing@gmx.de>
 */
class ilObjectConsumerTableGUI implements DataRetrieval
{
    private readonly Factory $factory;
    private readonly Renderer $renderer;
    private readonly ServerRequestInterface $request;
    private readonly Services $http;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly ilLanguage $lng;
    private readonly ilCtrl $ctrl;

    private bool $editable = true;

    public function __construct(private readonly object $parent, private readonly string $parent_cmd)
    {
        global $DIC;

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
    }

    public function setEditable(bool $status): void
    {
        $this->editable = $status;
    }

    /**
     * @return list<array{
     *     id: int,
     *     active: bool,
     *     title: string,
     *     description: string,
     *     prefix: string,
     *     language: string,
     *     objects: string,
     *     role: string
     * }>
     */
    private function getRecords(): array
    {
        $connector = new ilLTIDataConnector();
        $consumer_data = $connector->getGlobalToolConsumerSettings();

        $records = [];
        foreach ($consumer_data as $cons) {
            $obj_types = ilObjLTIAdministration::getActiveObjectTypes($cons->getExtConsumerId());
            $objects = [];
            foreach ($obj_types as $obj_type) {
                $objects[] = $this->lng->txt('objs_' . $obj_type);
            }

            $role_title = '';
            $role = ilObjectFactory::getInstanceByObjId($cons->getRole(), false);
            if ($role instanceof ilObjRole) {
                $role_title = $role->getTitle();
            }

            $records[] = [
                'id' => (int) $cons->getExtConsumerId(),
                'active' => (bool) $cons->getActive(),
                'title' => (string) $cons->getTitle(),
                'description' => (string) $cons->getDescription(),
                'prefix' => (string) $cons->getPrefix(),
                'language' => (string) $cons->getLanguage(),
                'objects' => $objects ? implode(', ', $objects) : '-',
                'role' => $role_title
            ];
        }

        return $records;
    }

    // DataRetrieval implementation
    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data = null,
        ?array $additional_parameters = null
    ): \Generator {
        $records = $this->getRecords();

        // simple ordering
        if ($order) {
            [$order_field, $order_dir] = $order->join([], fn($ret, $k, $v) => [$k, $v]);
            usort($records, static fn($a, $b) => ($a[$order_field] <=> $b[$order_field]));
            if ($order_dir === Order::DESC) {
                $records = array_reverse($records);
            }
        }

        if ($range) {
            $records = array_slice($records, $range->getStart(), $range->getLength());
        }

        foreach ($records as $record) {
            $row = $row_builder->buildDataRow((string) $record['id'], $record);
            if ($this->editable) {
                $row = $row
                    ->withDisabledAction('activate', $record['active'])
                    ->withDisabledAction('deactivate', !$record['active']);
            }
            yield $row;
        }
    }

    public function getTotalRowCount(?array $filter_data = null, ?array $additional_parameters = null): ?int
    {
        return count($this->getRecords());
    }

    /** @return array<string, Column> */
    private function getColumns(): array
    {
        return [
            'active' => $this->factory->table()->column()->boolean(
                $this->lng->txt('active'),
                $this->lng->txt('yes'),
                $this->lng->txt('no')
            ),
            'title' => $this->factory->table()->column()->text($this->lng->txt('title')),
            'description' => $this->factory->table()->column()->text($this->lng->txt('description')),
            'prefix' => $this->factory->table()->column()->text($this->lng->txt('prefix')),
            'language' => $this->factory->table()->column()->text($this->lng->txt('in_use')),
            'objects' => $this->factory->table()->column()->text($this->lng->txt('objects')),
            'role' => $this->factory->table()->column()->text($this->lng->txt('role'))
        ];
    }

    /** @return array<string, \ILIAS\UI\Component\Table\Action\Action> */
    private function getActions(
        URLBuilder $url_builder,
        URLBuilderToken $action_token,
        URLBuilderToken $row_id_token
    ): array {
        if (!$this->editable) {
            return [];
        }

        return [
            'edit' => $this->factory->table()->action()->single(
                $this->lng->txt('edit'),
                $url_builder->withParameter($action_token, 'edit'),
                $row_id_token
            ),
            'delete' => $this->factory->table()->action()->single(
                $this->lng->txt('delete'),
                $url_builder->withParameter($action_token, 'delete'),
                $row_id_token
            ),
            'activate' => $this->factory->table()->action()->single(
                $this->lng->txt('activate'),
                $url_builder->withParameter($action_token, 'activate'),
                $row_id_token
            ),
            'deactivate' => $this->factory->table()->action()->single(
                $this->lng->txt('deactivate'),
                $url_builder->withParameter($action_token, 'deactivate'),
                $row_id_token
            )
        ];
    }

    public function getHTML(string $url): string
    {
        $df = new \ILIAS\Data\Factory();
        $table_uri = $df->uri($url);
        $url_builder = new URLBuilder($table_uri);

        [$url_builder, $action_token, $row_id_token] = $url_builder->acquireParameters(
            ['cid'],
            'table_action',
            'id'
        );

        // handle triggered actions
        $query = $this->http->wrapper()->query();
        if ($query->has($action_token->getName())) {
            $action = $query->retrieve($action_token->getName(), $this->refinery->to()->string());
            $id = $query->retrieve($row_id_token->getName(), $this->refinery->to()->string());
            $this->ctrl->setParameter($this->parent, 'cid', $id);

            switch ($action) {
                case 'edit':
                    $this->ctrl->redirect($this->parent, 'editConsumer');
                    break;
                case 'delete':
                    $this->ctrl->redirect($this->parent, 'deleteLTIConsumer');
                    break;
                case 'activate':
                case 'deactivate':
                    $this->ctrl->redirect($this->parent, 'changeStatusLTIConsumer');
                    break;
            }
        }

        $table = $this->factory
            ->table()
            ->data($this->lng->txt('lti_object_consumer'), $this->getColumns(), $this)
            ->withId(self::class)
            ->withOrder(new Order('title', Order::ASC))
            ->withActions($this->getActions($url_builder, $action_token, $row_id_token))
            ->withRequest($this->request);

        return $this->renderer->render($table);
    }
}

