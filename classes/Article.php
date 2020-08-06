<?php
/**
 * Article
 *
 * A piece of writing for publication
 *
 */
class Article
{
    /**
     * Unique identifier
     * @var integer
     */
    public $id;

    /**
     * Article title
     * @var string
     */
    public $title;

    /**
     * Article content
     * @var string
     */
    public $content;

    /**
     * Publication date and time
     * @var datetime
     */
    public $published_at;

    /**
     * Path to image
     * @var string
     */
    public $image_file;

    /**
     * Validation Errors
     * @var array
     */
    public $errors = [];

    /**
     * Get all the articles
     *
     * @param object $conn Connection to the database
     *
     * @return array An associative array of all the article records
     */
    public static function getAll($conn)
    {
        $sql = "SELECT *
            FROM articles
            ORDER BY published_at;
        ";

        $results = $conn->query($sql);

        return $results->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get page of articles
     *
     * @param object $conn Connection to the database
     * @param integer $limit Number of records to return
     * @param integer $offset number of records to skip
     *
     * @return array An assiciative array of the page of article records
     */
    public static function getPage($conn, $limit, $offset, $published_only = false)
    {
        $condition = $published_only ? " WHERE published_at IS NOT NULL" : '';
        
        $sql = "SELECT a.*, category.name AS category_name
                FROM (SELECT *
                    FROM articles
                    $condition
                    ORDER BY published_at
                    LIMIT :limit
                    OFFSET :offset) AS a
                LEFT JOIN article_category
                    ON a.id = article_category.article_id
                LEFT JOIN category
                    ON article_category.category_id = category.id
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $articles = [];
        $previous_id = null;
        
        foreach ($results as $row) {
            $article_id = $row['id'];

            if ($article_id != $previous_id) {
                $row['category_names'] = [];               

                $articles[$article_id] = $row;
            }

            $articles[$article_id]['category_names'][] = $row['category_name'];

            $previous_id = $article_id;
        }
        
        return $articles;
    }

    /**
     * Get the article record based on the ID
     *
     * @param object $conn Connection to the database
     * @param integer $id Article ID
     * @param string $solumns Optional List of columns for the select, default * (all)
     *
     * @return mixed An object array of this class, or null if not found
     *
     */
    public static function getById($conn, $id, $columns = "*")
    {
        $sql = "SELECT $columns
                FROM articles
                WHERE id = :id
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Article');

        if ($stmt->execute()) {
            return $stmt->fetch();
        }
    }

    /**
     * Get the article records based on the ID along with associated categories, if any
     * 
     * @param object $conn Connection to the database
     * @param integer $id The article ID
     * 
     * @return array The article data with categories (this just returns an array of values)
     */
    public static function getWithCategories($conn, $id, $only_published = false) {
        $sql = "SELECT articles.*, category.name AS category_name
                FROM articles
                LEFT JOIN article_category
                    ON articles.id = article_category.article_id
                LEFT JOIN category
                    ON article_category.category_id = category.id
                WHERE articles.id = :id
        ";

        if ($only_published) {
            $sql .= " AND articles.published_at IS NOT NULL";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the article's categories
     * 
     * @param object $conn Connection to the database
     * 
     * @return array The category data (this returns an object)
     */
    public function getCategories($conn) {
        $sql = "SELECT category.*
                FROM category
                JOIN article_category
                ON category.id = article_category.category_id
                WHERE article_id = :id
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update the article with its current property values
     *
     * @param object $conn Connection to the database
     *
     * @return boolean True if the update was successful, false otherwise
     *
     */
    public function update($conn)
    {
        if ($this->validate()) {
            $sql = "UPDATE articles
                SET title = :title,
                    content = :content,
                    published_at = :published_at
                WHERE id = :id
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindValue(':title', $this->title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $this->content, PDO::PARAM_STR);

            if ($this->published_at == '') {
                $stmt->bindValue(':published_at', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':published_at', $this->published_at, PDO::PARAM_STR);
            }

            return $stmt->execute();
        } else {
            return false;
        }
    }

    /**
     * Set the article categories
     * 
     * @param object $conn Connection to the database
     * @param array $ids Category IDs
     * 
     * @return void
     */
    public function setCategories($conn, $ids) {
        if ($ids) {
            $sql = "INSERT IGNORE INTO article_category (article_id, category_id)
                    VALUES
            ";

            $values = [];

            foreach ($ids as $id) {
                $values[] = "({$this->id}, ?)";
            }

            $sql .= implode(", ", $values);

            $stmt = $conn->prepare($sql);

            foreach ($ids as $i => $id) {
                $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
            }

            $stmt->execute();
        }

        // delete categories not selected on form
        $sql = "DELETE FROM article_category
                WHERE article_id = {$this->id}
        ";

        if ($ids) {
            $placeholders = array_fill(0, count($ids), '?');

            $sql .= " AND category_id NOT IN (" . implode(", ", $placeholders) . ")";
        }

        $stmt = $conn->prepare($sql);

        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }

        $stmt->execute();


    }

    /**
     * Validate the article parameters
     *
     * @param string $title Title, required
     * @param string $content Content, required
     * @param string $published_at Published dat and time, yyyy-mm-dd hh:mm:ss if not blank
     *
     * @return boolean True if the current properties are valid, false otherwise
     *
     */
    protected function validate()
    {
        if ($this->title == '') {
            $this->errors[] = "Title is required";
        }

        if ($this->content == '') {
            $this->errors[] = "Content is required";
        }

        if ($this->published_at != '') {
            $date_time = date_create_from_format('Y-m-d H:i:s', $this->published_at);

            if ($date_time === false) {
                $date_errors = date_get_last_errors();

                if ($date_errors['warning_count'] > 0) {
                    $this->errors[] = "Invalid Publication Date";
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Delete the current article
     *
     * @param object $conn Connection to the database
     *
     * @return boolean True if the delete is successful, false otherwise
     *
     */
    public function delete($conn)
    {
        $sql = "DELETE FROM articles
                WHERE id = :id
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Insert a new article with its current property values
     *
     * @param object $conn Connection to the database
     *
     * @return boolean True if the update was successful, false otherwise
     *
     */
    public function create($conn)
    {
        if ($this->validate()) {
            $sql = "INSERT INTO articles (title, content, published_at)
                    VALUES (:title, :content, :published_at)
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bindValue(':title', $this->title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $this->content, PDO::PARAM_STR);

            if ($this->published_at == '') {
                $stmt->bindValue(':published_at', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':published_at', $this->published_at, PDO::PARAM_STR);
            }

            if ($stmt->execute()) {
                $this->id = $conn->lastInsertId();
                return true;
            };
        } else {
            return false;
        }
    }

    /**
     * Get a count of the total number of records
     *
     * @param object $conn Connection to the database
     *
     * @return integer The total number of records
     */
    public static function getTotal($conn, $published_only = false)
    {
        $condition = $published_only ? ' WHERE published_at IS NOT NULL' : '';

        return $conn->query(
                "SELECT COUNT(*) 
                 FROM articles 
                 $condition"
            )->fetchColumn();
    }

    /**
    * Update the image file property
    *
    * @param object $conn Connection to the database
    * @param string $filename The filename of the image file
    *
    * @return boolean True if it was successful, false otherwise
    */
    public function setImageFile($conn, $filename)
    {
        $sql = "UPDATE articles
                SET image_file = :image_file
                WHERE id = :id;
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':image_file', $filename, $filename == null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Publish the article, setting the published_at field to the current date and time
     * 
     * @param object $conn Connection to the database
     * 
     * @return mixed The published at data and time if successful, null otherwise
     */
    public function publish($conn) {
        $sql = "UPDATE articles
                SET published_at = :published_at
                WHERE id = :id;
            ";

        $stmt = $conn->prepare($sql);

        $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

        $published_at = date("Y-m-d H:i:s");
        $stmt->bindValue(':published_at', $published_at, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return $published_at;
        }

    }
}
