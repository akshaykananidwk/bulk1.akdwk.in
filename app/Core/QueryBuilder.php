<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fluent SQL builder. Everything compiles to prepared statements;
 * identifiers are quoted, values always bound.
 */
final class QueryBuilder
{
    private string $table;
    private array $columns = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private array $groups = [];
    private ?string $havingSql = null;
    private array $havingBindings = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private string $lockSql = '';

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    // -- Select components ---------------------------------------------------

    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function selectRaw(RawExpression $expression): self
    {
        $this->columns = [$expression];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = strtoupper($type) . ' JOIN ' . $this->wrapTable($table)
            . ' ON ' . $this->wrap($first) . ' ' . $this->operator($operator) . ' ' . $this->wrap($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            return $operator === '=' ? $this->whereNull($column, $boolean) : $this->whereNotNull($column, $boolean);
        }
        $this->wheres[] = [$boolean, $this->wrap($column) . ' ' . $this->operator((string) $operator) . ' ?'];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (empty($values)) {
            // Impossible condition keeps semantics correct
            $this->wheres[] = [$boolean, $not ? '1 = 1' : '1 = 0'];
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [$boolean, $this->wrap($column) . ($not ? ' NOT IN (' : ' IN (') . $placeholders . ')'];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'AND', true);
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, $this->wrap($column) . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, $this->wrap($column) . ' IS NOT NULL'];
        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        $this->wheres[] = ['AND', $this->wrap($column) . ' BETWEEN ? AND ?'];
        $this->bindings[] = $from;
        $this->bindings[] = $to;
        return $this;
    }

    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, $this->wrap($column) . ' LIKE ?'];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Raw where fragment — developer-authored SQL only, values bound.
     */
    public function whereRaw(RawExpression $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, '(' . $sql->value . ')'];
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }
        return $this;
    }

    /**
     * Grouped conditions: ->whereGroup(fn($q) => $q->where(...)->orWhere(...))
     */
    public function whereGroup(callable $callback, string $boolean = 'AND'): self
    {
        $sub = new self($this->table);
        $callback($sub);
        if ($sub->wheres) {
            $sql = $sub->compileWheres(true);
            $this->wheres[] = [$boolean, '(' . $sql . ')'];
            foreach ($sub->bindings as $binding) {
                $this->bindings[] = $binding;
            }
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->wrap($column) . ' ' . $direction;
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->wrap($column);
        }
        return $this;
    }

    public function havingRaw(RawExpression $sql, array $bindings = []): self
    {
        $this->havingSql = $sql->value;
        $this->havingBindings = $bindings;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = max(0, $offset);
        return $this;
    }

    public function lockForUpdate(bool $skipLocked = false): self
    {
        $this->lockSql = ' FOR UPDATE' . ($skipLocked ? ' SKIP LOCKED' : '');
        return $this;
    }

    // -- Terminal operations -------------------------------------------------

    public function get(): array
    {
        return DB::select($this->toSql(), $this->allBindings());
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        if ($row === null) {
            return null;
        }
        // Column may be aliased or dotted
        $key = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
        return $row[$key] ?? array_values($row)[0] ?? null;
    }

    public function pluck(string $column): array
    {
        $rows = $this->select($column)->get();
        $key = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
        return array_map(fn ($row) => $row[$key] ?? array_values($row)[0] ?? null, $rows);
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = [new RawExpression('COUNT(*) AS aggregate')];
        $clone->orders = [];
        $clone->limitValue = null;
        $clone->offsetValue = null;
        $row = DB::selectOne($clone->toSql(), $clone->allBindings());
        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $clone = clone $this;
        $clone->columns = [new RawExpression('COALESCE(SUM(' . $clone->wrap($column) . '), 0) AS aggregate')];
        $clone->orders = [];
        $row = DB::selectOne($clone->toSql(), $clone->allBindings());
        return (float) ($row['aggregate'] ?? 0);
    }

    public function max(string $column): mixed
    {
        $clone = clone $this;
        $clone->columns = [new RawExpression('MAX(' . $clone->wrap($column) . ') AS aggregate')];
        $clone->orders = [];
        $row = DB::selectOne($clone->toSql(), $clone->allBindings());
        return $row['aggregate'] ?? null;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Offset pagination: returns ['data', 'total', 'page', 'per_page', 'last_page'].
     */
    public function paginate(int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        return [
            'data' => $this->get(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $this->wrapTable($this->table)
            . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        DB::statement($sql, array_values($data));
        return DB::lastInsertId();
    }

    /**
     * Bulk insert with chunking (500 rows per statement).
     */
    public function insertMany(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        $columns = array_keys($rows[0]);
        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $sql = 'INSERT INTO ' . $this->wrapTable($this->table)
                . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ')'
                . ' VALUES ' . implode(', ', array_fill(0, count($chunk), $placeholderRow));
            $bindings = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $bindings[] = $row[$column] ?? null;
                }
            }
            $count += DB::statement($sql, $bindings);
        }
        return $count;
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $sets = [];
        $setBindings = [];
        foreach ($data as $column => $value) {
            if ($value instanceof RawExpression) {
                $sets[] = $this->wrap($column) . ' = ' . $value->value;
            } else {
                $sets[] = $this->wrap($column) . ' = ?';
                $setBindings[] = $value;
            }
        }
        $sql = 'UPDATE ' . $this->wrapTable($this->table) . ' SET ' . implode(', ', $sets);
        if ($this->wheres) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        return DB::statement($sql, array_merge($setBindings, $this->bindings));
    }

    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $data = array_merge($extra, [
            $column => new RawExpression($this->wrap($column) . ' + ' . (is_int($amount) ? $amount : sprintf('%F', $amount))),
        ]);
        return $this->update($data);
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->wrapTable($this->table);
        if ($this->wheres) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        return DB::statement($sql, $this->bindings);
    }

    // -- SQL compilation -----------------------------------------------------

    public function toSql(): string
    {
        $columns = implode(', ', array_map(
            fn ($col) => $col instanceof RawExpression ? $col->value : ($col === '*' ? '*' : $this->wrap((string) $col)),
            $this->columns
        ));

        $sql = 'SELECT ' . $columns . ' FROM ' . $this->wrapTable($this->table);

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->havingSql !== null) {
            $sql .= ' HAVING ' . $this->havingSql;
        }
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }
        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql . $this->lockSql;
    }

    public function allBindings(): array
    {
        return array_merge($this->bindings, $this->havingBindings);
    }

    private function compileWheres(bool $forSub = false): string
    {
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $fragment]) {
            $sql .= ($i === 0 ? '' : ' ' . $boolean . ' ') . $fragment;
        }
        return $sql;
    }

    private function operator(string $operator): string
    {
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        $upper = strtoupper($operator);
        if (!in_array($upper, $allowed, true)) {
            throw new \InvalidArgumentException('Illegal SQL operator: ' . $operator);
        }
        return $upper;
    }

    private function wrap(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            [$table, $column] = explode('.', $identifier, 2);
            return $this->quote($table) . '.' . ($column === '*' ? '*' : $this->quote($column));
        }
        return $this->quote($identifier);
    }

    private function wrapTable(string $table): string
    {
        // Support "table alias" form
        if (preg_match('/^(\S+)\s+(?:AS\s+)?(\w+)$/i', $table, $m)) {
            return $this->quote(DB::prefix() . $m[1]) . ' AS ' . $this->quote($m[2]);
        }
        return $this->quote(DB::prefix() . $table);
    }

    private function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Illegal SQL identifier: ' . $identifier);
        }
        return '`' . $identifier . '`';
    }
}
