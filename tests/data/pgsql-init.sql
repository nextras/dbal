CREATE TABLE "second_schema"."authors" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	"web" varchar(100) NOT NULL,
	"born" date DEFAULT NULL,
	PRIMARY KEY("id")
);


CREATE TABLE "publishers" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	PRIMARY KEY("id")
);

CREATE UNIQUE INDEX ON "public"."publishers" ("name");

CREATE TABLE "tags" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	PRIMARY KEY ("id")
);

CREATE TABLE "eans" (
	"id" INT4 NOT NULL GENERATED ALWAYS AS IDENTITY,
	"code" varchar(50) NOT NULL,
	PRIMARY KEY("id")
);

CREATE TABLE "books" (
	"id" SERIAL4 NOT NULL,
	"author_id" int NOT NULL,
	"translator_id" int,
	"title" varchar(50) NOT NULL,
	"publisher_id" int NOT NULL,
	"ean_id" int,
	PRIMARY KEY ("id"),
	CONSTRAINT "books_authors" FOREIGN KEY ("author_id") REFERENCES "second_schema"."authors" ("id"),
	CONSTRAINT "books_translator" FOREIGN KEY ("translator_id") REFERENCES "second_schema"."authors" ("id"),
	CONSTRAINT "books_publisher" FOREIGN KEY ("publisher_id") REFERENCES publishers ("id"),
	CONSTRAINT "books_ean" FOREIGN KEY ("ean_id") REFERENCES eans ("id")
);

CREATE INDEX "book_title" ON "books" ("title");

CREATE VIEW "my_books" AS SELECT * FROM "books" WHERE "author_id" = 1;

CREATE TABLE "users" (
	"first_name" varchar NOT NULL,
	"last_name" varchar NOT NULL,
	"full_name" text GENERATED ALWAYS AS (
		(btrim("last_name") || ' ' || btrim("first_name"))
	) STORED NOT NULL
);

CREATE TABLE "books_x_tags" (
	"book_id" int NOT NULL,
	"tag_id" int NOT NULL,
	PRIMARY KEY ("book_id", "tag_id"),
	CONSTRAINT "books_x_tags_tag" FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT "books_x_tags_book" FOREIGN KEY ("book_id") REFERENCES "books" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE "tag_followers" (
	"tag_id" int NOT NULL,
	"author_id" int NOT NULL,
	"created_at" timestamptz NOT NULL,
	PRIMARY KEY ("tag_id", "author_id"),
	CONSTRAINT "tag_followers_tag" FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT "tag_followers_author" FOREIGN KEY ("author_id") REFERENCES "second_schema"."authors" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE "table_with_defaults" (
	"name" VARCHAR(255) DEFAULT 'Jon Snow'
);
