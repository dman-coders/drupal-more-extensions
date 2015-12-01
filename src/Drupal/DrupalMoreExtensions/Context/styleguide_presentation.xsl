<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:media="http://search.yahoo.com/mrss/">
  <xsl:output method="html"/>
  <xsl:param name="resource_dirpath"/>

  <xsl:template match="/">
    <html>
      <head>
        <link rel="stylesheet" type="text/css"
              href="styleguide_presentation.css">
          <xsl:attribute name="href"><xsl:value-of select="$resource_dirpath"/>styleguide_presentation.css</xsl:attribute>
        </link>
        <title>
          <xsl:apply-templates select="/rss/channel/title"/>
        </title>

        <style type="text/css">
          /*
            Inlining styles because relative links are hard.
            This is just enough to be notbroken.
          */
          /*
            For full css support, ensure the $resource_dirpath parameter
            is provided to the generator
            OR copy styleguide_presentation.css to the working directory.
          */

          .styleguide-screenshot {
            width: 60%;
            float: left;
          }
          .styleguide-screenshot img {
            max-width: 100%;
          }
          .styleguide-context {
            width: 30%;
            float: left;
          }
          .styleguide-context img {
            max-width: 100%;
          }
        </style>
      </head>
      <body>
        <h1>
          <xsl:apply-templates select="/rss/channel/title"/>
        </h1>
        <p>
          <xsl:apply-templates select="/rss/channel/description"/>
        </p>
        <ul>
          <xsl:apply-templates select="/rss/channel/item"/>
        </ul>
      </body>
    </html>

  </xsl:template>

  <xsl:template match="item">
    <li class="styleguide-item">
      <h2 class="styleguide-header">
        <xsl:value-of select="title"/>
      </h2>

      <div class="styleguide-screenshot">
        <div class="styleguide-inner">
          <xsl:apply-templates select="media:content[@isDefault = 'true']"/>
        </div>
      </div>
      <div class="styleguide-context">

        <a>
          <xsl:attribute name="href">
            <xsl:value-of select="link"/>
          </xsl:attribute>
          <xsl:apply-templates
              select="media:content[not(@isDefault = 'true')]"/>
        </a>

        <div class="link">
          <a>
          <xsl:attribute name="href">
            <xsl:value-of select="link"/>
          </xsl:attribute>
          <xsl:value-of select="link"/>
          </a>
        </div>

        <div class="description">
          <xsl:value-of disable-output-escaping="yes" select="description"/>
        </div>
      </div>
    </li>

  </xsl:template>

  <xsl:template match="media:content">
    <img>
      <xsl:attribute name="src">
        <xsl:value-of select="@url"/>
        <xsl:value-of select="@media:url"/>
      </xsl:attribute>
      <xsl:attribute name="title">
        <xsl:value-of select="@title"/>
        <xsl:value-of select="@media:title"/>
      </xsl:attribute>
    </img>
  </xsl:template>

</xsl:stylesheet>
